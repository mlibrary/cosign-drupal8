<?php

namespace Drupal\Tests\cosign\Unit;

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Config\ImmutableConfig;
use Psr\Log\LoggerInterface;
use Drupal\user\Entity\User;

/**
 * Tests for Cosign authentication provider.
 *
 * @group cosign
 */

class CosignUnitTest extends UnitTestCase {


  public function setUp() {
    parent::setUp();

    $this->cosign_user = $this->getRandomGenerator()->word(10);
    $this->password = $this->getRandomGenerator()->word(10);
    $this->email_domain = $this->getRandomGenerator()->word(5) . '.com';
    $this->is_friend = 0;
    $this->server_friend = NULL;
    $this->protossl = NULL;
    $this->redirect_remote_user = $this->cosign_user;
    $this->remote_user = $this->cosign_user;
    $this->logout_path = $this->getRandomGenerator()->word(10);
    $this->logout_to = $this->getRandomGenerator()->word(10);
  }
 
  /**
   * Test cosignNewUserVars function.
   */
  public function testcosignNewUserVars() {
    $this->is_friend = 0;
    $cosign_user = CosignSharedFunctions::cosignNewUserVars($this->cosign_user, $this->password, $this->email_domain, $this->is_friend);
    $this->assertEquals($cosign_user['name'], $this->cosign_user);
    $this->assertEquals($cosign_user['status'], 1);
    $this->assertEquals($cosign_user['password'], $this->password);
    $this->assertEquals($cosign_user['mail'], $this->cosign_user.'@'.$this->email_domain);

    $this->is_friend = 1;
    $cosign_user = CosignSharedFunctions::cosignNewUserVars($this->cosign_user, $this->password, $this->email_domain, $this->is_friend);
    $this->assertEquals($cosign_user['mail'], $this->cosign_user);
  }

  /**
   * Test cosignCheckFriendAccount function.
   */
  public function testcosignCheckFriendAccount() {
    $this->server_friend = NULL;
    $user_is_friend = CosignSharedFunctions::cosignCheckFriendAccount($this->cosign_user.'@'.$this->email_domain, $this->server_friend);
    $this->assertEquals($user_is_friend, TRUE);

    $user_is_friend = CosignSharedFunctions::cosignCheckFriendAccount($this->cosign_user, $this->server_friend);
    $this->assertEquals($user_is_friend, FALSE);

    $this->server_friend = 'friend';
    $user_is_friend = CosignSharedFunctions::cosignCheckFriendAccount($this->cosign_user, $this->server_friend);
    $this->assertEquals($user_is_friend, TRUE);
  }

  /**
   * Test cosignCheckHttps function.
   */
  public function testcosignCheckHttps() {
    $this->protossl = NULL;
    $is_https = CosignSharedFunctions::cosignCheckHttps($this->protossl);
    $this->assertEquals($is_https, FALSE);

    $this->protossl = 's';
    $is_https = CosignSharedFunctions::cosignCheckHttps($this->protossl);
    $this->assertEquals($is_https, TRUE);
  }

  /**
   * Test cosignGetRemoteUser function.
   */
  public function testcosignGetRemoteUser() {
    $this->redirect_remote_user = $this->cosign_user;
    $this->remote_user = $this->cosign_user;
    $get_remote_user = CosignSharedFunctions::cosignGetRemoteUser($this->redirect_remote_user, $this->remote_user);
    $this->assertEquals($get_remote_user, $this->cosign_user);

    $this->remote_user = NULL;
    $get_remote_user = CosignSharedFunctions::cosignGetRemoteUser($this->redirect_remote_user, $this->remote_user);
    $this->assertEquals($get_remote_user, $this->cosign_user);

    $this->redirect_remote_user = NULL;
    $get_remote_user = CosignSharedFunctions::cosignGetRemoteUser($this->redirect_remote_user, $this->remote_user);
    $this->assertNotEquals($get_remote_user, $this->cosign_user);

    $this->remote_user = $this->cosign_user;
    $get_remote_user = CosignSharedFunctions::cosignGetRemoteUser($this->redirect_remote_user, $this->remote_user);
    $this->assertEquals($get_remote_user, $this->cosign_user);
  }

  /**
   * Test cosignGetLogoutUrl function.
   */
  public function testcosignGetLogoutUrl() {
    $get_logout = CosignSharedFunctions::cosignGetLogoutUrl($this->logout_path, $this->logout_to);
    $this->assertEquals($get_logout, $this->logout_path .'?'. $this->logout_to);
  }    

}
