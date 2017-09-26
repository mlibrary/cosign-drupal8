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
    $request_uri = $event->getRequest()->getRequestUri();
    if (strpos($request_uri, 'user/login') || strpos($request_uri, 'user/register')) {
      $response = $event->getResponse();
      if (!CosignSharedFunctions::cosign_is_https() 
        //&& strpos($response->getTargetUrl(), 'ttps://')
      ) {
        //settargeturl will not work if not an event from a redirect
        //the controller takes care of a straight user/login url
        //we can intercept the redirect route here and throw to https
        //there may be a better way to handle this
//        if (!strpos($response->getTargetUrl(), 'user/login') || !strpos($response->getTargetUrl(), 'user/register')) {
          $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $request_uri;
          $response->setTrustedTargetUrl($https_url);
//        }
      }
      else {
        $destination = \Drupal::destination()->getAsArray()['destination'];
        $username = CosignSharedFunctions::cosign_retrieve_remote_user();
        global $base_path;
        if (!$username) {
          $request_uri = \Drupal::config('cosign.settings')->get('cosign_login_path').'?cosign-'.$_SERVER['HTTP_HOST'].'&https://'.$_SERVER['HTTP_HOST'];
          if ($destination == $base_path.'user/login' || $destination == $base_path.'user/register') {
            $destination = $base_path;
          }
          $request_uri = $request_uri . $destination;
        }
        else {
          CosignSharedFunctions::cosign_user_status($username);
          if ($request_uri == $base_path.'user/login' || $request_uri == $base_path.'user/register') {
            $request_uri = $base_path;
          }
          else {
            $request_uri = $destination;
          }
        }
        if ($response instanceOf TrustedRedirectResponse) {
           $response->setTrustedTargetUrl($request_uri);
        }
        else {
          $event->setResponse(new TrustedRedirectResponse($request_uri));
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