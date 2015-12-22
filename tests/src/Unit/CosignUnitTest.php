<?php

/**
 * @file
 * Contains \Drupal\Tests\cosign\Unit\CosignUnitTest.
 */

namespace Drupal\Tests\cosign\Unit;

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Drupal\cosign\Tests\CosignTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Drupal\Tests\Core\EventSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
/**
 *
 * Tests for Cosign authentication provider.
 *
 * @group cosign
 */
//if (!class_exists("CosignUnitTest")) {
class CosignUnitTest extends UnitTestCase {

  //use CosignTestTrait;

  /**
   * Modules installed for all tests.
   *
   * @var array
   */
  public static $modules = array('cosign', 'router_test', 'locale');

  /**
   * Test http cosign.
   */
  public function testCosign() {
    // Enable page caching.
//    $config = $this->config('system.performance');
//    $config->set('cache.page.max_age', 300);
//    $config->save();

    global $_SERVER;
    $_SERVER['REDIRECT_REMOTE_USER'] = 'test';
    $_SERVER['REMOTE_USER'] = 'test';
    $_SERVER['REMOTE_REALM'] = '';

$autoloader = require_once '/autoload.php';
$kernel = new DrupalKernel('prod', $autoloader);
$request = Request::createFromGlobals();

    //These are the factory defaults in cosign.settings.yml
    \Drupal::configFactory()->getEditable('cosign.settings')
      ->set('cosign_autocreate', 0)
      ->set('cosign_allow_anons_on_https', 0)
      ->set('cosign_allow_cosign_anons', 0)
      ->set('cosign_allow_friend_accounts', 0)
      ->set('cosign_friend_account_message', 'Friend accounts are not allowed on this site')
      
      //For these we should test based on the admin entered value
      //->set('cosign_logout_path', 'https://weblogin.umich.edu/cgi-bin/logout')
      //->set('cosign_login_path', 'https://weblogin.umich.edu/')
      
      //This is set in hook_install in cosign.module
      //->set('cosign_logout_to', '')
      
      //These don't really matter for testing
      //->set('cosign_branded', 'Cosign')
      //->set('cosignautocreate_email_domain', 'umich.edu')
      
      //Not using these (Yet?). Here in case they are needed later
      //->set('cosign_invalid_login', 'access-denied')
      //->set('cosign_autologout', 1)
      //->set('cosign_invalid_login_message', 'Your cosign user name is invalid')
      
      ->save();







//$response = $kernel->handle($request);
$account = CosignSharedFunctions::cosign_user_status(CosignSharedFunctions::cosign_retrieve_remote_user());
$request_uri = $request->getRequestUri();
$test = new TrustedRedirectResponse('https://' . $_SERVER['HTTP_HOST'] . $request_uri);
$hmm = testDestinationRedirect($request, '/');
exit(print_r($hmm));
    //The user returned should be anonymous as we dont allow auto creation of users by default
    $account = CosignSharedFunctions::cosign_user_status(CosignSharedFunctions::cosign_retrieve_remote_user());
    $url = Url::fromRoute('router_test.11', array(), array('https'=> FALSE));
    $this->CosignGet($url, $account->getUsername(), $account->pass_raw);
    $this->assertEqual($account->getUsername(), '');
    $this->assertResponse('200', 'Anon http OK');
    $this->curlClose();
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertIdentical(strpos($this->drupalGetHeader('Cache-Control'), 'public'), FALSE, 'Cache-Control is not set to public');

    //The user should be redirected to https and sent to cosign for login here
    $account = CosignSharedFunctions::cosign_user_status(CosignSharedFunctions::cosign_retrieve_remote_user());
    $url = Url::fromRoute('router_test.11', array(), array('https'=> TRUE));
    $this->CosignGet($url, $account->getUsername(), $account->pass_raw);
    $this->assertEqual($account->getUsername(), '');
    $this->assertResponse('200', 'User taken to '.$this->getUrl().' over https');
    $this->curlClose();
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertIdentical(strpos($this->drupalGetHeader('Cache-Control'), 'public'), FALSE, 'Cache-Control is not set to public');

    //The user should be created and logged in(?) here
    \Drupal::configFactory()->getEditable('cosign.settings')
      ->set('cosign_autocreate', 1)
      ->save();
    $account = CosignSharedFunctions::cosign_user_status(CosignSharedFunctions::cosign_retrieve_remote_user());
  }

}
//}