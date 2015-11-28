<?php
namespace Drupal\cosign\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;

class CosignSubscriber implements EventSubscriberInterface {

  public function checkRedirection(FilterResponseEvent $event) {
    if (!CosignSharedFunctions::cosign_is_https()) {
      $request_uri = \Drupal::request()->getRequestUri();
      if (strpos($request_uri, 'user/login')) {
        $response = $event->getResponse();
        $response->setTargetUrl('https://' . $_SERVER['SERVER_NAME'] . $request_uri);
      }
    }
    else {
      $username = CosignSharedFunctions::cosign_retrieve_remote_user();
      if ($username) {
        $is_friend_account = CosignSharedFunctions::cosign_is_friend_account($username);
        // If friend accounts are not allowed, log them out
        if (\Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts') == 0 && $is_friend_account) {
          CosignSharedFunctions::cosign_friend_not_allowed();
        }
        else {
          CosignSharedFunctions::cosign_user_status($username);
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