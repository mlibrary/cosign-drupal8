<?php

namespace Drupal\cosign\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

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
   * The cosign shared service.
   *
   * @var \Drupal\cosign\CosignFunctions\CosignSharedFunctions
   */
   protected $cosignShared;

  /**
   * Constructs a Cosign provider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\cosign\CosignFunctions\CosignSharedFunctions $cosign_shared
   *   The cosign shared service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UserAuthInterface $user_auth, EntityManagerInterface $entity_manager, CosignSharedFunctions $cosign_shared) {
    $this->configFactory = $config_factory;
    $this->userAuth = $user_auth;
    $this->entityManager = $entity_manager;
    $this->cosignShared = $cosign_shared;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    if ($request->cookies->get('Drupal_visitor_cosign-'.str_replace('.', '_', $_SERVER['HTTP_HOST'])) == 'anon') {
      return FALSE;
    }
    $username = $this->cosignShared->cosignRetrieveRemoteUser();
    $drupal_user = user_load_by_name($username);
    // This session variable is set and sticks even after user_logout().
    // If we put cosign priority after the user module
    // (priority 0 or below in services.yml),
    // the symfony session sticks and the previous user gets logged in.
    // If we put it above the user module (above priority 0)
    // the user gets relogged in every time because
    // drupal's session hasn't been set yet...even though symfony's has.
    // TODO This should be the proper way to get this but it doesnt get it -
    /* $symfony_uid = $request->getSession()-> get('_sf2_attributes'); */
    if (isset($_SESSION['_sf2_attributes']['uid']) && !empty($drupal_user) && $drupal_user->id() == $_SESSION['_sf2_attributes']['uid']) {
      // The user is already logged in. symfony knows, drupal doesnt yet.
      // Bypass cosign so we dont login again.
      return FALSE;
    }
    if ($request->cookies->get('cosign-' . str_replace('.', '_', $_SERVER['HTTP_HOST'])) == 'jibberish') {
      return FALSE;
    }
    if ($this->cosignShared->cosignIsHttps() &&
        $request->getRequestUri() != '/user/logout' &&
        ($this->configFactory->get('cosign.settings')->get('cosign_allow_cosign_anons') == 0 ||
        $this->configFactory->get('cosign.settings')->get('cosign_allow_anons_on_https') == 0 ||
        $request->cookies->get('cosign-' . str_replace('.', '_', $_SERVER['HTTP_HOST'])) != 'jibberish' ||
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
    $username = $this->cosignShared->cosignRetrieveRemoteUser();
    if ($user = $this->cosignShared->cosignUserStatus($username)) {
      return $user;
    }
    else {
      if (!$this->cosignShared->cosignIsFriendAccount($username)) {
        $cosign_brand = $this->configFactory->get('cosign.settings')->get('cosign_branded');
        drupal_set_message(t('This site is restricted. You may try <a href="/user/login">logging in to @cosign_brand</a>.', [@cosign_brand => $cosign_brand]), 'error');
      }
      throw new AccessDeniedHttpException();
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
