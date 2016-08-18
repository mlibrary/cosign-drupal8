<?php
/**
 * @file
 * Contains \Drupal\cosign\Routing\RouteSubscriber.
 */

namespace Drupal\cosign\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if (\Drupal::config('cosign.settings')->get('cosign_ban_password_resets')) {
      if($route = $collection->get('user.pass')){
        $route->setRequirement('_access', 'FALSE');
      }
      if($route = $collection->get('user.reset')){
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
?>