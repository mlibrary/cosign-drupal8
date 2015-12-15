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
use Drupal\cosign\Form\CosignLogout;
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
      $cosign_brand = \Drupal::config('cosign.settings')->get('cosign_branded');
      $request->attributes->get(\Symfony\Cmf\Component\Routing\RouteObjectInterface::ROUTE_OBJECT)->setDefault('_title', 'Would you like to logout of '.$cosign_brand.' also?');
      $form_class = '\Drupal\cosign\Form\CosignLogout';
      $response['form'] = \Drupal::formBuilder()->getForm($form_class);
    }
    if (!empty($uname)){
      drupal_set_message('User '.$uname.' successfully logged out of '.\Drupal::config("system.site")->get("name"), 'status', FALSE);
    }
    user_logout();
    return $response;
  }

  //Send this over to an event handler after forcing https.
  public function cosign_login(Request $request) {
    $request_uri = $request->getRequestUri();
    global $base_path;
    if (!CosignSharedFunctions::cosign_is_https()) {
      return new TrustedRedirectResponse('https://' . $_SERVER['HTTP_HOST'] . $request_uri);
    }
    else {
      if ($request_uri == $base_path){
        //The front page is set to /user. we have to login here to avoid a redirect loop
        $username = CosignSharedFunctions::cosign_retrieve_remote_user();
        $user = CosignSharedFunctions::cosign_user_status($username);
        if (empty($user) || $user->id() == 0) {
          $cosign_brand = \Drupal::config('cosign.settings')->get('cosign_branded');
          $response = array(
            '#type' => 'markup',
            '#title' => 'Auto creation of user accounts is disabled.',
            '#markup' => t('<p>This site does not auto create users from '.$cosign_brand.'. Please contact the <a href="mailto:'. \Drupal::config("system.site")->get("mail").'">site administrator</a> to have an account created.</p>'),
          );
          return $response;
        }
        else {
          //admin role can now be named anything
          $is_admin = array_intersect(\Drupal::entityQuery('user_role')->condition('is_admin', TRUE)->execute(), $user->getRoles());
          if (!empty($is_admin) && \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
            drupal_set_message('When the homepage is set to /user (Drupal default), anonymous browsing will not always work', 'warning');
          }
          $referrer = $base_path.'user';
        }
      }
      elseif (isset($_SERVER['HTTP_REFERER'])) {
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