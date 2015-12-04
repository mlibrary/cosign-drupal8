# cosign-drupal8
a module to utilize cosign for drupal logins

You need to add "  RewriteRule ^cosign/valid - [L] " to .htaccess below 

  RewriteEngine on
  RewriteRule ^cosign/valid - [L] 

