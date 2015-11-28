<?php

/**
 * @file
 * Contains \Drupal\cosign\Authentication\Provider\Cosign.
 */

namespace Drupal\cosign\Authentication\Provider;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\UserSession;
use Drupal\user\Authentication\Provider\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;

/**
 * Cosign authentication provider.
 */
class Cosign implements AuthenticationProviderInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user auth service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a Cosign provider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserAuthInterface $user_auth, EntityManagerInterface $entity_manager) {
    $this->configFactory = $config_factory;
    $this->userAuth = $user_auth;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    $username = CosignSharedFunctions::cosign_retrieve_remote_user();
    $drupal_user = user_load_by_name($username);
    //TODO need to get the symfony login uid properly as drupal's is not ready yet. 
    //This session variable is set and sticks even after user_logout() causing numerous problems. if we put cosign module priority after the user module (priority 0 or below in services.yml) the symfony session sticks and the previous user gets logged in. if we put it above the user module (above priority 0) the user gets relogged in every time because drupal's session hasn't been set yet...even though symfony's has!
    if ($drupal_user->id() == $_SESSION['_sf2_attributes']['uid']) {
      //the user is already logged in. symfony knows, drupal doesnt yet. bypass cosign so we dont login again 
      return FALSE;
    }
    if (\Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 0 && 
        $request->server->get('protossl') == 's' &&
        $request->getRequestUri() != '/user/logout' &&
        $request->getRequestUri() != '/user/login'
       ) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $username = CosignSharedFunctions::cosign_retrieve_remote_user();
    $is_friend_account = CosignSharedFunctions::cosign_is_friend_account($username);
    // If friend accounts are not allowed, log them out
    if (\Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts') == 0 && $is_friend_account) {
      CosignSharedFunctions::cosign_friend_not_allowed();
      return null;
    }
    return CosignSharedFunctions::cosign_user_status($username);
  }
}
