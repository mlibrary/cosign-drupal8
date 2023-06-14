# cosign-drupal
a module to utilize cosign for drupal logins, updated for drupal 9.x

## installation
   * Check out / unzip into `web/modules/cosign`
   * Enable module if required!
   * Configure module (in "system") to point to your Cosign web service

## .htaccess changes

   * Add `RewriteRule ^cosign/valid - [L]` immediately below `RewriteEngine on` to prevent Drupal handling cosign requests.
   * _If you wish to enforce use of SSL_ you should include the old Drupal 7 boilerplate:
```
  RewriteRule ^ - [E=protossl]
  RewriteCond %{HTTPS} on
  RewriteRule ^ - [E=protossl:s]
```
   note this doesn't yet work, it has been disabled.  Recommend forcing HTTPS at all times, anyway.

## TODO:
   * instructions and metadata for composer installation
   * run through https://www.drupal.org/project/drupalmoduleupgrader to check for other deprecation.
   * make the tests work; expand them
   * remove silly debug logs
   * fix HTTP/HTTPS detection - without this 'force https' mode won't work (workaround, and recommendation, is to server your site over only HTTPS with an HTTP -> HTTPS redirect)
   * TODO: use a variant of the protossl environment variable to enforce a specific vhost for cosign comms


