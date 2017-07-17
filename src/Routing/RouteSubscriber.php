<?php

namespace Drupal\cosign\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a Cosign event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($this->configFactory->get('cosign.settings')->get('cosign_ban_password_resets')) {
      if ($route = $collection->get('user.pass')) {
        $route->setRequirement('_access', 'FALSE');
      }
      if ($route = $collection->get('user.reset')) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
