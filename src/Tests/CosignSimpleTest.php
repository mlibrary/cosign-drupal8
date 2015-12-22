<?php

/**
 * @file
 * Contains \Drupal\cosign\Tests\CosignSimpleTest.
 */

namespace Drupal\cosign\Tests;

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Drupal\cosign\Tests\CosignTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\simpletest\WebTestBase;
use Drupal\cosign\CosignFunctions\CosignSharedFunctions;
use Drupal\Tests\Core\EventSubscriber;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Drupal\Tests\Core\EventSubscriber\RedirectResponseSubscriberTest;
/**
 * Tests for Cosign authentication provider.
 *
 * @group cosign
 */
class CosignSimpleTest extends WebTestBase {
  /**
   * Test destination detection and redirection.
   *
   * @param Request $request
   *   The request object with destination query set.
   * @param string|bool $expected
   *   The expected target URL or FALSE.
   *
   * @covers ::checkRedirectUrl
   * @dataProvider providerTestDestinationRedirect
   *
  public function testDestinationRedirect($request, $expected) {
    $dispatcher = new EventDispatcher();
    $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
    $response = new RedirectResponse('http://example.com/drupal');
    $request->headers->set('HOST', 'example.com');

    $listener = new RedirectResponseSubscriber($this->urlAssembler, $this->requestContext);
    $dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'checkRedirectUrl'));
    $event = new FilterResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
    $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

    $target_url = $event->getResponse()->getTargetUrl();
    if ($expected) {
      $this->assertEquals($expected, $target_url);
    }
    else {
      $this->assertEquals('http://example.com/drupal', $target_url);
    }
  }
  */
  use CosignTestTrait;

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
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    global $_SERVER;
    $_SERVER['REDIRECT_REMOTE_USER'] = 'test';
    $_SERVER['REMOTE_USER'] = 'test';
    $_SERVER['REMOTE_REALM'] = '';

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




$autoloader = require_once 'autoload.php';

$kernel = new DrupalKernel('prod', $autoloader);

$request = Request::createFromGlobals();
//$response = $kernel->handle($request);
$account = CosignSharedFunctions::cosign_user_status(CosignSharedFunctions::cosign_retrieve_remote_user());
$request_uri = $request->getRequestUri();
$test = new TrustedRedirectResponse('https://' . $_SERVER['HTTP_HOST'] . $request_uri);
$hmm = RedirectResponseSubscriberTest::testDestinationRedirect($request, '/');
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
    $this->CosignGet($url, $account->getUsername(), $account->pass_raw);
    $this->assertText($account->getUsername(), 'Successful account creation. Cosign account name is displayed.');
    $this->assertResponse('200', 'HTTP response is OK');
    $this->curlClose();
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertIdentical(strpos($this->drupalGetHeader('Cache-Control'), 'public'), FALSE, 'Cache-Control is not set to public');

    //The user password should be bad here
    $this->CosignGet($url, $account->getUsername(), $this->randomMachineName());
    $this->assertNoText($account->getUsername(), 'Bad basic auth credentials do not authenticate the user.');
    $this->assertResponse('403', 'Access is not granted.');
    $this->curlClose();
/*
//TODO not sure if I can spoof a cosign cookie
    $this->drupalGet($url);
    $this->assertEqual($this->drupalGetHeader('cosign-'.$_SERVER['HTTP_HOST']), SafeMarkup::format('Basic realm="@realm"', ['@realm' => \Drupal::config('system.site')->get('name')]));
    $this->assertResponse('401', 'Not authenticated on the route that allows only cosign. Prompt to authenticate received.');
*/
    $this->drupalGet('admin');
    $this->assertResponse('403', 'No authentication prompt for routes not explicitly defining authentication providers.');

    $account = $this->drupalCreateUser(array('access administration pages'));
    $account->setUsername(CosignSharedFunctions::cosign_retrieve_remote_user());

    $this->CosignGet(Url::fromRoute('system.admin'), $account->getUsername(), $account->pass_raw);
    $this->assertNoLink('Log out', 'User is not logged in');
    $this->assertResponse('403', 'No cosign for routes not explicitly defining authentication providers.');
    $this->curlClose();

    // Ensure that pages already in the page cache aren't returned from page
    // cache if basic auth credentials are provided.
    $url = Url::fromRoute('router_test.10');
    $this->drupalGet($url);
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->CosignGet($url, $account->getUsername(), $account->pass_raw);
    $this->assertFalse($this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertIdentical(strpos($this->drupalGetHeader('Cache-Control'), 'public'), FALSE, 'No page cache response when requesting a cached page with basic auth credentials.');
  }

  /**
   * Test the per-user login flood control.
   */
  function testPerUserLoginFloodControl() {
    $this->config('user.flood')
      // Set a high global limit out so that it is not relevant in the test.
      ->set('ip_limit', 4000)
      ->set('user_limit', 2)
      ->save();

    $user = $this->drupalCreateUser(array());
    $incorrect_user = clone $user;
    $incorrect_user->pass_raw .= 'incorrect';
    $user2 = $this->drupalCreateUser(array());
    $url = Url::fromRoute('router_test.11');

    // Try a failed login.
    $this->CosignGet($url, $incorrect_user->getUsername(), $incorrect_user->pass_raw);

    // A successful login will reset the per-user flood control count.
    $this->CosignGet($url, $user->getUsername(), $user->pass_raw);
    $this->assertResponse('200', 'Per user flood prevention gets reset on a successful login.');

    // Try 2 failed logins for a user. They will trigger flood control.
    for ($i = 0; $i < 2; $i++) {
      $this->CosignGet($url, $incorrect_user->getUsername(), $incorrect_user->pass_raw);
    }

    // Now the user account is blocked.
    $this->CosignGet($url, $user->getUsername(), $user->pass_raw);
    $this->assertResponse('403', 'The user account is blocked due to per user flood prevention.');

    // Try one successful attempt for a different user, it should not trigger
    // any flood control.
    $this->CosignGet($url, $user2->getUsername(), $user2->pass_raw);
    $this->assertResponse('200', 'Per user flood prevention does not block access for other users.');
  }

  /**
   * Tests compatibility with locale/UI translation.
   */
  function testLocale() {
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->config('system.site')->set('default_langcode', 'de')->save();

    $account = $this->drupalCreateUser();
    $url = Url::fromRoute('router_test.11');

    $this->CosignGet($url, $account->getUsername(), $account->pass_raw);
    $this->assertText($account->getUsername(), 'Account name is displayed.');
    $this->assertResponse('200', 'HTTP response is OK');
    $this->curlClose();
  }

  /**
   * Tests if a comprehensive message is displayed when the route is denied.
   */
  function testUnauthorizedErrorMessage() {
    $account = $this->drupalCreateUser();
    $url = Url::fromRoute('router_test.11');

    // Case when no credentials are passed.
    $this->drupalGet($url);
    $this->assertResponse('401', 'The user is blocked when no credentials are passed.');
    $this->assertNoText('Exception', "No raw exception is displayed on the page.");
    $this->assertText('Please log in to access this page.', "A user friendly access unauthorized message is displayed.");

    // Case when empty credentials are passed.
    $this->CosignGet($url, NULL, NULL);
    $this->assertResponse('403', 'The user is blocked when empty credentials are passed.');
    $this->assertText('Access denied', "A user friendly access denied message is displayed");

    // Case when wrong credentials are passed.
    $this->CosignGet($url, $account->getUsername(), $this->randomMachineName());
    $this->assertResponse('403', 'The user is blocked when wrong credentials are passed.');
    $this->assertText('Access denied', "A user friendly access denied message is displayed");
  }



}
