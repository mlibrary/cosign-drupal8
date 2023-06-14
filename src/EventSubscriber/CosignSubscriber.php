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
    $referer = $event->getRequest()->server->get('HTTP_REFERER');
    if (strpos($request_uri, 'user/login') || strpos($request_uri, 'user/register')) {
      $response = $event->getResponse();

      // GD -- causes redirect loop -- must investigate as this is untrue!
      // if (!CosignSharedFunctions::cosign_is_https()
      if (false
      //

        //&& strpos($response->getTargetUrl(), 'ttps://')
      ) {
        //settargeturl will not work if not an event from a redirect
        //the controller takes care of a straight user/login url
        //we can intercept the redirect route here and throw to https
        //there may be a better way to handle this
//        if (!strpos($response->getTargetUrl(), 'user/login') || !strpos($response->getTargetUrl(), 'user/register')) {
          $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $request_uri;
          #error_log("intercepting {$event->getRequest()}..."); #$request_uri)
          $response->setTrustedTargetUrl($https_url);
//        }
      }
      else {
        $destination = \Drupal::destination()->getAsArray()['destination'];
        $username = CosignSharedFunctions::cosign_retrieve_remote_user();
        global $base_path;
        $base_url = rtrim($_SERVER['HTTP_HOST'], '/'). '/';
        if (!$username) {
          $request_uri = \Drupal::config('cosign.settings')->get('cosign_login_path').'?cosign-'.$_SERVER['HTTP_HOST'].'&https://'.$base_url;
          error_log("*** NO USERNAME, REDIRECTING TO $request_uri ***");
          if ($destination == $base_path.'user/login' || $destination == $base_path.'user/register') {
            $destination = str_replace('https://'.$base_url,'',$referer);
          }
          $request_uri = $request_uri . $destination;
          error_log("*** CHANGED TO $request_uri ***");
        }
        else {
          error_log("*** GOT USER {$username} ***");
          CosignSharedFunctions::cosign_user_status($username);
          if ($request_uri == $base_path.'user/login' || $request_uri == $base_path.'user/register') {
            $request_uri = $referer;
          }
          else {
            $request_uri = $destination;
          }
        }
        if (empty($request_uri) || strpos($request_uri, 'user/logout')) {
          $request_uri = '/';
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
