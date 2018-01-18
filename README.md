# Packagistlab

Making a packagist server out of your gitlab instance.

## Install

On a webserver run

```
git clone https://gl.zt.je/eater/packagistlab.git;
composer install;
"${EDITOR:-vim}" config/config.php;
```

Now create a server config which points to `/public`,

<details> 
    <summary>nginx config example</summary>
    <pre>
server {
    listen 80;
    server_name composer.my.domain;
    location / {
        return 301 https://$server_name$request_uri;
    }
}
server {
    server_name  composer.my.domain;
    root         /sites/my.domain/public;
    include certs/composer.zt.je;
    index index.php index.html;
    location / {
        return 301 https://gitlab.my.domain/;
    }
    location /packages.json {
        try_files $uri =404;
    }
    location /gitlab-callback.php {
        fastcgi_pass   unix:/var/run/php-fpm.sock;
        fastcgi_index  gitlab-callback.php;
        include        fastcgi.conf;
        try_files $uri =404;
    }
}
    </pre>
</details>

To create a seed `packages.json` you can go to `https://composer.my.domain/gitlab-callback.php?secret=<YOUR-SECRET>` or run `php public/gitlab-callback.php "<YOUR-SECRET>"`

Now in your gitlab instance, go to "Admin" > "System Hooks" and add `https://composer.my.domain/gitlab-callback.php?secret=<YOUR-SECRET>` for Push and Tag Push events.

Once you've done that. it should all automatically and you can run on your own desktop or wherever `composer config --global repositories.my-domain composer https://composer.my.domain/` to add this packagist repo.