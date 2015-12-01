<?php
/**
 * @file
 * Contains \Drupal\cosign\Controller\CosignController.
 */

namespace Drupal\cosign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Symfony\Component\HttpFoundation\Cookie;

class CosignController extends ControllerBase {

  public function cosign_logout() {
    $logout = CosignSharedFunctions::cosign_logout_url();
    user_logout();
    $response = new TrustedRedirectResponse($logout);
    //this had to be done of user was logged into cosign/drupal for several minutes after logging out
    //for ref since this was hard to find - Cookie($name, $value, $minutes, $path, $domain, $secure, $httpOnly)
    //set value to nonsense and domain to blank so it becomes a host cookie.
    //set the expiration to -1 so it immediately expires. otherwise, cosign has a hard time overwriting if you hit Go Back from the cosign logout screen (at umich at least)
    $response->headers->setCookie(new Cookie('cosign-'.$_SERVER['HTTP_HOST'], 'jibberish', 0, '/', '', -1, 0));

    return $response;
  }

  public function cosign_login() {
    $username = CosignSharedFunctions::cosign_retrieve_remote_user();
    //if cosign username is empty, go to https://weblogin.umich.edu/?cosign-eliotwsc-drupal8.www.lib.umich.edu&https://eliotwsc-drupal8.www.lib.umich.edu/content/test
    if (!$username && \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
      $request_uri = \Drupal::config('cosign.settings')->get('cosign_login_path').'?cosign-'.$_SERVER['HTTP_HOST'].'&https://'.$_SERVER['HTTP_HOST'];
      if ($destination = \Drupal::destination()->getAsArray()['destination'] && $destination != 'user/login') {
        $request_uri = $request_uri . $destination;
      }
      return new TrustedRedirectResponse($request_uri);
    }
    CosignSharedFunctions::cosign_user_status($username);
    $request_uri = \Drupal::request()->getRequestUri();
    if ($request_uri == '/user/login' && $username) {
      $request_uri = '/';
    }
    return new RedirectResponse($request_uri);
  }
}
?>