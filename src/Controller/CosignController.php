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
use Drupal\Core\Url;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserAuthInterface;

use Drupal\Core\Routing\RedirectDestination;

use Drupal\Core\Session\SessionManager;

use Drupal\cosign\CosignFunctions\CosignSharedFunctions;

use Symfony\Component\HttpFoundation\Cookie;

class CosignController extends ControllerBase {

  public function cosign_logout() {
    user_logout();
    $logout_path = \Drupal::config('cosign.settings')->get('cosign_logout_path');
    $logout_to = \Drupal::configFactory()->getEditable('cosign.settings')->get('cosign_logout_to');
    $logout = $logout_path . '?' . $logout_to;
    $response = new TrustedRedirectResponse($logout);
    //this had to be done of user was logged into cosign/drupal for several minutes after logging out
    //for ref since this was hard to find - Cookie($name, $value, $minutes, $path, $domain, $secure, $httpOnly)
    //set value to nonsense and domain to blank so it becomes a host cookie.
    //set the expiration to -1 so it immediately expires. otherwise, cosign has a hard time overwriting if you hit Go Back from the cosign logout screen (at umich at least)
    $response->headers->setCookie(new Cookie('cosign-eliotwsc-drupal8.www.lib.umich.edu', 'huhuh', 0, '/', '', -1, 0));

    return $response;
  }

  public function cosign_login() {
    if ($_SERVER['protossl'] != 's') {
      return new RedirectResponse('https://' . $_SERVER['SERVER_NAME'] . '/user/login');
    }
    if (\Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
      $username = CosignSharedFunctions::cosign_retrieve_remote_user();
      $is_friend_account = CosignSharedFunctions::cosign_is_friend_account($username);
      // If friend accounts are not allowed, log them out
      if (\Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts') == 0 && $is_friend_account) {
        CosignSharedFunctions::cosign_friend_not_allowed();
        return null;
      }
      CosignSharedFunctions::cosign_user_status($username);
    }
    $redirect_path = CosignSharedFunctions::cosign_redirect();
    return new RedirectResponse($redirect_path);
  }
}
?>