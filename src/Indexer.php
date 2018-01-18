<?php


class Indexer
{
    private $client;
    private $parser;

    public function __construct($gitlabUri, $apiToken)
    {
        $this->parser = new Composer\Semver\VersionParser();
        $this->client = new GuzzleHttp\Client([
            'base_uri' => $gitlabUri . '/api/v4/',
            'headers' => [
                'PRIVATE-TOKEN' => $apiToken,
            ],
            'http_errors' => false,
        ]);
    }

    public function indexAll() {
        $packagesJson = [];
        $page = 1;
        $pageLimit = 1;

        while ($page <= $pageLimit) {
            $projects = $this->client->get('projects?per_page=100&page=' . $page);

            $projectList = \GuzzleHttp\json_decode($projects->getBody(), true);


            foreach ($projectList as $project) {
                $packagesJson = array_merge_recursive($packagesJson, $this->index($project));
            }

            $pageLimit = intval($projects->getHeaderLine('x-total-pages'));
            $page++;
        }
        
        return $packagesJson;
    }

    public function index($projectObject) {
        $projectBaseUrl = $projectObject['_links']['self'];

        $resp = $this->client->get($projectBaseUrl . '/repository/files/composer.json?ref=master');

        if ($resp->getStatusCode() !== 200) {
            # No composer.json found in master, stop looking
            return [];
        }

        $packageJson = [];

        $tags = \GuzzleHttp\json_decode($this->client->get($projectBaseUrl . '/repository/tags')->getBody(), true);

        foreach ($tags as $tag) {
            $packageJson = array_merge_recursive($packageJson, $this->createPackageVersion($projectObject, $tag));
        }

        $branches = \GuzzleHttp\json_decode($this->client->get($projectBaseUrl . '/repository/branches')->getBody(), true);

        foreach ($branches as $branch) {
            $packageJson = array_merge_recursive($packageJson, $this->createPackageVersion($projectObject, $branch, 'dev-'));
        }

        return $packageJson;
    }

    public function createPackageVersion($project, $object, $prefix = '') {
        $projectBaseUrl = $project['_links']['self'];

        $resp = $this->client->get($projectBaseUrl . '/repository/files/composer.json/raw?ref=' . $object['name']);
        if ($resp->getStatusCode() !== 200) {
            # No composer.json in this tag
            return [];
        }

        // Make sure forks get put in their own namespace


        $package = \GuzzleHttp\json_decode($resp->getBody(), true);

        $packageName = $package['name'];
        $parts = explode('/', $project['path_with_namespace']);
        $packageParts = explode('/', $packageName);
        $parent = strtolower($parts[0]);
        $packageParts[0] = $parent;

        $package['name'] = implode('/', $packageParts);
        $package['version'] = $prefix . $object['name'];
        $package['version_normalized'] = $this->parser->normalize($package['version']);

        $package['source'] = [
            'type' => 'git',
            'url' => $project['visibility'] === 'public' ? $project['http_url_to_repo'] : $project['ssh_url_to_repo'],
            'reference' => $object['commit']['id']
        ];

        $package['dist'] = null;

        if ($project['visibility'] === 'public') {
            $package['dist'] = [
                'type' => 'zip',
                'url' => 'https://gl.zt.je/eater/shoarma/repository/' . urlencode($object['name']) . '/archive.zip',
                'reference' => $object['commit']['id'],
                'shasum' => "",
            ];
        }

        return [$package['name'] => [$package['version'] => $package]];
    }
}