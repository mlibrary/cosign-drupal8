<?php

namespace Drupal\cosign\Controller;

use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Drupal\cosign\Form\CosignLogout;
use Symfony\Component\HttpFoundation\Cookie;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for cosign routes.
 */
class CosignController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The formBuilder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity query factory service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

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
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   * @param \Drupal\cosign\CosignFunctions\CosignSharedFunctions $cosign_shared
   *   The cosign shared service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FormBuilderInterface $form_builder, AccountProxyInterface $current_user, QueryFactory $entity_query, CosignSharedFunctions $cosign_shared) {
    $this->configFactory = $config_factory;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->entityQuery = $entity_query;
    $this->cosignShared = $cosign_shared;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('entity.query'),
      $container->get('cosign.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function cosignLogout(Request $request) {
    $uname = $this->currentUser->getAccountName();
    if ($this->configFactory->get('cosign.settings')->get('cosign_allow_cosign_anons') == 0 ||
       ($this->cosignShared->cosignIsFriendAccount($uname) &&
       $this->configFactory->get('cosign.settings')->get('cosign_allow_friend_accounts') == 0)) {
      $response = CosignController::cosignCosignLogout();
    }
    else {
      $cosign_brand = $this->configFactory->get('cosign.settings')->get('cosign_branded');
      $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)->setDefault('_title', 'Would you like to logout of ' . $cosign_brand . ' also?');
      $form_class = '\Drupal\cosign\Form\CosignLogout';
      $response['form'] = $this->formBuilder->getForm($form_class);
    }
    if (!empty($uname)) {
      drupal_set_message($this->t('User @uname successfully logged out of @sitename', ['@uname' => $uname, '@sitename' => $this->configFactory->get("system.site")->get("name")]), 'status', FALSE);
    }
    user_logout();
    return $response;
  }

  /**
   * {@inheritdoc}
   */

  /**
   * Send this over to an event handler after forcing https.
   */
  public function cosignLogin(Request $request) {
    $request_uri = $request->getRequestUri();
    global $base_path;
    if (!$this->cosignShared->cosignIsHttps()) {
      return new TrustedRedirectResponse('https://' . $_SERVER['HTTP_HOST'] . $request_uri);
    }
    else {
      if ($request_uri == $base_path) {
        // The front page is set to /user.
        // We have to login here to avoid a redirect loop.
        $username = $this->cosignShared->cosignRetrieveRemoteUser();
        $user = $this->cosignShared->cosignUserStatus($username);
        if (!$user) {
          throw new AccessDeniedHttpException();
        }
        if ($user->id() == 0) {
          $cosign_brand = $this->configFactory->get('cosign.settings')->get('cosign_branded');
          $response = [
            '#type' => 'markup',
            '#title' => 'Auto creation of user accounts is disabled.',
            '#markup' => $this->t('<p>This site does not auto create users from @cosign_brand. Please contact the <a href="mailto:@site_mail">site administrator</a> to have an account created.</p>', ['@cosign_brand' => $cosign_brand, '@site_mail' => $this->configFactory->get("system.site")->get("mail")]),
          ];
          return $response;
        }
        else {
          // Admin role can now be named anything.
          $is_admin = array_intersect($this->entityQuery->get('user_role')->condition('is_admin', TRUE)->execute(), $user->getRoles());
          if (!empty($is_admin) && $this->configFactory->get('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
            drupal_set_message($this->t('When the homepage is set to /user (Drupal default), anonymous browsing will not always work'), 'warning');
          }
          $referrer = $base_path . 'user';
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

  /**
   * {@inheritdoc}
   */
  public function cosignCosignLogout() {
    $logout = $this->cosignShared->cosignLogoutUrl();
    user_logout();
    $response = new TrustedRedirectResponse($logout);
    // User was logged into cosign/drupal for several minutes after logging out
    // Ref:Cookie($name, $value, $minutes, $path, $domain, $secure, $httpOnly)
    // set value to nonsense and domain to blank so it becomes a host cookie.
    $response->headers->setCookie(new Cookie('cosign-' . $_SERVER['HTTP_HOST'], 'jibberish', 0, '/', '', -1, 0));
    return $response;
  }

}
