# cosign-drupal
a module to utilize cosign for drupal logins
# updated for drupal 9.x

You need to add "RewriteRule ^cosign/valid - [L] " to .htaccess below "RewriteEngine on"

The tests (both simple and phpunit) do not currently work

TODO: run through https://www.drupal.org/project/drupalmoduleupgrader to check for other deprecation.
