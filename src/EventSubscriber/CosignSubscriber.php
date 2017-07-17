<?php

namespace Drupal\cosign\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cosign event subscriber.
 */
class CosignSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The destination interface.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destinationInterface;

  /**
   * The cosign shared service.
   *
   * @var \Drupal\cosign\CosignFunctions\CosignSharedFunctions
   */
   protected $cosignShared;

  /**
   * Constructs a Cosign event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination_interface
   *   The destination interface.
   * @param \Drupal\cosign\CosignFunctions\CosignSharedFunctions $cosign_shared
   *   The cosign shared service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RedirectDestinationInterface $destination_interface, CosignSharedFunctions $cosign_shared) {
    $this->configFactory = $config_factory;
    $this->destinationInterface = $destination_interface;
    $this->cosignShared = $cosign_shared;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRedirection(FilterResponseEvent $event) {
    $request_uri = $event->getRequest()->getRequestUri();
    if (strpos($request_uri, 'user/login') || strpos($request_uri, 'user/register')) {
      $response = $event->getResponse();
      if (!$this->cosignShared->cosignIsHttps()
        // && strpos($response->getTargetUrl(), 'ttps://')
      ) {
        // Settargeturl will not work if not an event from a redirect
        // the controller takes care of a straight user/login url
        // we can intercept the redirect route here and throw to https
        // there may be a better way to handle this
        // if (!strpos($response->getTargetUrl(), 'user/login') ||
        // !strpos($response->getTargetUrl(), 'user/register')) {.
        $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $request_uri;
        $response->setTargetUrl($https_url);
        // }.
      }
      else {
        $destination = $this->destinationInterface->getAsArray()['destination'];
        $username = $this->cosignShared->cosignRetrieveRemoteUser();
        global $base_path;
        if (!$username && $this->configFactory->get('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
          $request_uri = $this->configFactory->get('cosign.settings')->get('cosign_login_path') . '?cosign-' . $_SERVER['HTTP_HOST'] . '&https://' . $_SERVER['HTTP_HOST'];
          if ($destination == $base_path . 'user/login' || $destination == $base_path . 'user/register') {
            $destination = $base_path;
          }
          $request_uri = $request_uri . $destination;
        }
        else {
          if ($user = $this->cosignShared->cosignUserStatus($username)) {
            if ($request_uri == $base_path . 'user/login' || $request_uri == $base_path . 'user/register') {
              $request_uri = $base_path;
            }
            else {
              $request_uri = $destination;
            }
          }
          else {
            throw new AccessDeniedHttpException();
          }
        }
        if ($response instanceof TrustedRedirectResponse) {
          $response->setTargetUrl($request_uri);
        }
        else {
          $event->setResponse(new TrustedRedirectResponse($request_uri));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkRedirection'];
    return $events;
  }

}
