<?php

/**
 * @file
 * Contains \Drupal\cosign\PageCache\DisallowCosignRequests.
 */

namespace Drupal\cosign\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;

/**
 * Cache policy for pages served from cosign.
 *
 * This policy disallows caching of requests that use cosign for security
 * reasons. Otherwise responses for authenticated requests can get into the
 * page cache and could be delivered to unprivileged users.
 *
 * // TODO
 * This was copied and altered from basic_auth. Not sure if it is really neccessary though
 */
class DisallowCosignRequests implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    $username = CosignSharedFunctions::cosign_retrieve_remote_user();
    if (isset($username) && $username != '') {
      return self::DENY;
    }
  }

}
