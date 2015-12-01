<?php
namespace Drupal\cosign\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;

class CosignSubscriber implements EventSubscriberInterface {

  public function checkRedirection(FilterResponseEvent $event) {
    $request_uri = \Drupal::request()->getRequestUri();
    if (strpos($request_uri, 'user/login')) {
      $response = $event->getResponse();
      if (!CosignSharedFunctions::cosign_is_https()) {
        //settargeturl will not work if not an event from a redirect (see CosignController::cosign_login controller also)
        //there may be a better way to handle this
        $response->setTargetUrl('https://' . $_SERVER['SERVER_NAME'] . $request_uri);
      }
      else {
        //if cosign username is empty, go to https://weblogin.umich.edu/?cosign-eliotwsc-drupal8.www.lib.umich.edu&https://eliotwsc-drupal8.www.lib.umich.edu/content/test
        if (!CosignSharedFunctions::cosign_retrieve_remote_user() && \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
          $request_uri = \Drupal::config('cosign.settings')->get('cosign_login_path').'?cosign-'.$_SERVER['HTTP_HOST'].'&https://'.$_SERVER['HTTP_HOST'].$request_uri;
          exit($request_uri);
          new TrustedRedirectResponse($request_uri);
        }
        if ($request_uri == '/user/login') {
          $request_uri = '/';
          $response->setTargetUrl($request_uri);
        }
        elseif ($request_uri = \Drupal::destination()) {
          new RedirectResponse($request_uri);
        }
        
      }
    }
  }

  static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = array('checkRedirection');
    return $events;
  }
}
?>