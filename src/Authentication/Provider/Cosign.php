<?php

/**
 * @file
 * Contains \Drupal\cosign\Authentication\Provider\Cosign.
 */

namespace Drupal\cosign\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Session\Session;

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
    //This session variable is set and sticks even after user_logout() causing numerous problems. if we put cosign module priority after the user module (priority 0 or below in services.yml) the symfony session sticks and the previous user gets logged in. if we put it above the user module (above priority 0) the user gets relogged in every time because drupal's session hasn't been set yet...even though symfony's has.
    //TODO This should be the proper way to get this but it doesnt get it -
    //$symfony_uid = $request->getSession()-> get('_sf2_attributes');
    if (isset($_SESSION['_sf2_attributes']['uid']) && !empty($drupal_user) && $drupal_user->id() == $_SESSION['_sf2_attributes']['uid']) {
      //the user is already logged in. symfony knows, drupal doesnt yet. bypass cosign so we dont login again      
      return FALSE;
    }
    if (CosignSharedFunctions::cosign_is_https() &&
        $request->getRequestUri() != '/user/logout' &&
        (\Drupal::config('cosign.settings')->get('cosign_allow_cosign_anons') == 0 ||
        \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 0 ||
        strpos($request->headers->get('referer'),$this->configFactory->get('cosign.settings')->get('cosign_login_path')) !== FALSE ||
        strpos($request->getRequestUri(), 'user/login') ||
        strpos($request->getRequestUri(), 'user/register'))
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
    if ($user = CosignSharedFunctions::cosign_user_status($username)) {
      return $user;
    }
    else {
      if (!CosignSharedFunctions::cosign_is_friend_account($username)){
        $cosign_brand = \Drupal::config('cosign.settings')->get('cosign_branded');
        drupal_set_message(t('This site is restricted. You may try <a href="/user/login">logging in to '.$cosign_brand.'</a>.'), 'error');
      }
      throw new AccessDeniedHttpException();
      return null;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(Request $request) {}

  /**
   * {@inheritdoc}
   */
  public function handleException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    if ($exception instanceof AccessDeniedHttpException) {
      return TRUE;
    }
    return FALSE;
  }
}
