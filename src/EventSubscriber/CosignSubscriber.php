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
    drupal_set_message('request_uri1 - '.$request_uri);
    $referer = $event->getRequest()->server->get('HTTP_REFERER');
    drupal_set_message('referer1 - '.$referer);
    if (strpos($request_uri, 'user/login') || strpos($request_uri, 'user/register')) {
      $response = $event->getResponse();
      drupal_set_message('response1 - '.$response);
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
        drupal_set_message('destination1 - '.$destination);
        $username = CosignSharedFunctions::cosign_retrieve_remote_user();
        global $base_path;
        if (empty($referer)) {
          $referer = $base_path;
        }
        $base_url = rtrim($_SERVER['HTTP_HOST'], '/'). '/';
        if (!$username) {
          $request_uri = \Drupal::config('cosign.settings')->get('cosign_login_path').'?cosign-'.$_SERVER['HTTP_HOST'].'&https://'.$base_url;
          if ($destination == $base_path.'user/login' || $destination == $base_path.'user/register') {
            $destination = str_replace('https://'.$base_url,'',$referer);
          }
          $request_uri = $request_uri . $destination;
        }
        else {
          CosignSharedFunctions::cosign_user_status($username);
          if (strpos($request_uri, $base_path.'user/login') !== FALSE || strpos($request_uri, $base_path.'user/register') !== FALSE) {
            drupal_set_message('referer2 - '.$referer);
            $uri_array = parse_url($request_uri);
            if (!empty($uri_array['query'])) {
              $referer .= $uri_array['query'];
            }
            $request_uri = $referer;
            drupal_set_message('request_uri2 - '.$request_uri);
          }
          else {
            $request_uri = $destination;
            drupal_set_message('request_uri3 - '.$request_uri);
          }
        }
        if (empty($request_uri) || strpos($request_uri, 'user/logout')) {
          $request_uri = '/';
        }
        if ($response instanceOf TrustedRedirectResponse) {
          drupal_set_message('request_uri4 - '.$request_uri);
          // we go into endless loop land here
          $response->setTrustedTargetUrl($request_uri);
        }
        else {
          drupal_set_message('request_uri5 - '.$request_uri);
          // we go into endless loop land here
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