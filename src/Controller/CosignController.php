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

  public function cosign_logout(Request $request) {
    $uname = \Drupal::currentUser()->getAccountName();
    if (\Drupal::config('cosign.settings')->get('cosign_allow_cosign_anons') == 0 ||
       (CosignSharedFunctions::cosign_is_friend_account($uname) && 
       \Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts') == 0)
      )
    {
      $response = CosignController::cosign_cosignlogout();
    }
    else {
      if (isset($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
      }
      else {
        global $base_path;
        $referrer = $base_path;
      }
      //$response = new RedirectResponse($referrer);
      //TODO - use $link = Link::fromTextAndUrl($text, $url);
      $response = array(
        '#type' => 'markup',
        '#title' => 'Browsing anonymously with cosign is enabled.',
        '#markup' => t('<p>To log out of cosign go to <a href="/cosign/logout">cosign/logout</a>.</p><p>To return where you were go to <a href="'.$referrer.'">'.$referrer.'</a>.</p>'),
      );
    }
    user_logout();
    return $response;
  }

  //Send this over to an event handler after forcing https.
  public function cosign_login(Request $request) {
    $request_uri = $request->getRequestUri();
    global $base_path;
    if ($request_uri == $base_path){
      $response = array(
        '#type' => 'markup',
        '#title' => 'The home page is set to /user.',
        '#markup' => t('<p>Cosign does not work well unless the home page is set to something else such as /node. It can be changed at <a href="'.$base_path.'admin/config/system/site-information">'.$base_path.'admin/config/system/site-information</a>.</p>'),
      );
      return $response;
    }
    if (!CosignSharedFunctions::cosign_is_https()) {
      return new TrustedRedirectResponse('https://' . $_SERVER['HTTP_HOST'] . $request_uri);
    }
    else {
      if (isset($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
      }
      else {
        $referrer = $base_path;
      }
      
      return new TrustedRedirectResponse($referrer);
    }
  }

  public function cosign_cosignlogout() {
    $logout = CosignSharedFunctions::cosign_logout_url();
    user_logout();
    $response = new TrustedRedirectResponse($logout);
    //this had to be done of user was logged into cosign/drupal for several minutes after logging out
    //for ref - Cookie($name, $value, $minutes, $path, $domain, $secure, $httpOnly)
    //set value to nonsense and domain to blank so it becomes a host cookie.
    $response->headers->setCookie(new Cookie('cosign-'.$_SERVER['HTTP_HOST'], 'jibberish', 0, '/', '', -1, 0));
    return $response;
  }
}
?>