<?php

namespace Drupal\cosign\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cache policy for pages served from cosign.
 *
 * This policy disallows caching of requests that use cosign for security
 * reasons. Otherwise responses for authenticated requests can get into the
 * page cache and could be delivered to unprivileged users.
 */
class DisallowCosignRequests implements RequestPolicyInterface {

  /**
   * The cosign shared service.
   *
   * @var \Drupal\cosign\CosignFunctions\CosignSharedFunctions
   */
   protected $cosignShared;

  /**
   * Constructs a Cosign disallow requests object.
   *
   * @param \Drupal\cosign\CosignFunctions\CosignSharedFunctions $cosign_shared
   *   The cosign shared service.
   */
   public function __construct(CosignSharedFunctions $cosign_shared) {
       $this->cosignShared = $cosign_shared;
   }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    $username = $this->cosignShared->cosignRetrieveRemoteUser();
    if (isset($username) && $username != '') {
      return self::DENY;
    }
  }

}
