# cosign-drupal
a module to utilize cosign for drupal logins, updated for drupal 9.x

## installation
   * Check out / unzip into `web/modules/cosign`
   * Modify .htaccess, adding `RewriteRule ^cosign/valid - [L]` immediately below `RewriteEngine on` to prevent Drupal handling these requests.
   * Enable module if required!
   * Configure module (in "system") to point to your Cosign service

## TODO:
   * instructions and metadata for composer installation
   * run through https://www.drupal.org/project/drupalmoduleupgrader to check for other deprecation.
   * make the tests work; expand them

