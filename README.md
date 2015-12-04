# cosign-drupal8
a module to utilize cosign for drupal logins

You need to add "  RewriteRule ^cosign/valid - [L] " to .htaccess below 

<IfModule mod_rewrite.c>
  RewriteEngine on
  RewriteRule ^cosign/valid - [L] 
