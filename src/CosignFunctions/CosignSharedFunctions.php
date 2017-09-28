<?php

namespace Drupal\cosign\CosignFunctions;

use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\user\Entity\User;

/**
 * Cosign shared functions.
 */
class CosignSharedFunctions {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Constructs a Cosign event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $http_request
   *   The request.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_interface
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user, RequestStack $http_request, LoggerChannelFactory $logger_interface) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->request = $http_request;
    $this->logger = $logger_interface;
  }

  /**
   * Check if user is logged into cosign, a drupal user, and logged into drupal.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   Account object
   */
  public function cosignUserStatus($cosign_username) {
    $user = NULL;
    $current_uname = $this->currentUser->getAccountName();
    $is_friend = $this->cosignIsFriendAccount($cosign_username);
    $allow_friends = $this->cosignGetSettings('cosign_allow_friend_accounts');
    $allow_anons = $this->cosignGetSettings('cosign_allow_anons_on_https');
    if (!$allow_friends && $is_friend) {
      $this->cosignFriendNotAllowed($cosign_username);
      $cosign_username = '';
    }
    if (empty($cosign_username) && $allow_anons) {
      $user = $this->cosignLoadUser(0);
    }
    elseif (($allow_friends || !$is_friend) && ($cosign_username == $current_uname)) {
      $user = $this->cosignLoadUser($cosign_username);
    }
    else {
      $this->cosignglobalfuncs('user_logout');
      $drupal_user = $this->cosignLoadUser($cosign_username);
      $autocreate = $this->cosignGetSettings('cosign_autocreate');
      if ($drupal_user) {
        $user = $drupal_user;
      }
      elseif ($autocreate) {
        $new_user =  $this->cosignCreateNewUser($cosign_username);
        $user = $this->cosignLoadUser($new_user->id());
      }
    }
    if ($user === NULL) {
       $this->cosignglobalfuncs('user_logout');
       return NULL;
    }

    return $this->cosignLogInUser($user);
  }

  /**
   * Helper function for cosignUserStatus.
   * Allows for easier testing.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   User object
   */
  public static function cosignGetUserStatus() {
    
  }

  /**
   * Logs cosign user into drupal.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   User Object
   */
  public function cosignLogInUser($drupal_user) {
    $username =  $this->cosignRetrieveRemoteUser();
    if ($drupal_user->getAccountName() != $username) {
      $this->logger->get('cosign')->notice('User attempted login and the cosign username: @remote_user, did not match the drupal username: @drupal_user', ['@remote_user' => $username, '@drupal_user' => $drupal_user->getAccountName()]);
      $this->cosignglobalfuncs('user_logout');
    }
    $this->cosignglobalfuncs('user_login_finalize', $drupal_user);

    return $this->currentUser;
  }

  /**
   * Performs tasks if friend accounts arent allowed.
   *
   * @return null
   *   NULL
   */
  public static function cosignFriendNotAllowed($username) {
    $this->logger->get('cosign')->notice('User attempted login using a university friend account and the friend account configuration setting is turned off: @remote_user', ['@remote_user' => $username]);
    drupal_set_message($this->cosignGetSettings('cosign_friend_account_message'), 'warning');
    if ($this->cosignGetSettings('cosign_allow_anons_on_https')) {
      $cosign_brand = $this->cosignGetSettings('cosign_branded');
      drupal_set_message(t('You might want to <a href="/user/logout">logout of @cosign_brand</a> to browse anonymously or as another @cosign_brand user.', ['@cosign_brand' => $cosign_brand]), 'warning');
    }
    else {
      user_logout();
      return NULL;
    }
  }

  /**
   * Cosign logout.
   *
   * @return url
   *   the logout url
   */
  public function cosignLogoutUrl() {
    $logout_path = $this->cosignGetSettings('cosign_logout_path');
    $logout_to = $this->cosignGetSettings('cosign_logout_to').'/';
    return self::cosignGetLogoutUrl($logout_path, $logout_to);
  }

  /**
   * Helper function for cosignLogoutUrl.
   * Allows for easier testing.
   *
   * @return url
   *   the logout url
   */
  public static function cosignGetLogoutUrl($logout_path, $logout_to) {
    return $logout_path . '?' . $logout_to;
  }

  /**
   * Attempts to retrieve the remote user from the $_SERVER variable.
   *
   * If the user is logged in to cosign webserver auth, the remote user variable
   * will contain the name of the user logged in.
   *
   * @return string
   *   String username or empty string.
   */
  public function cosignRetrieveRemoteUser() {
    $redirect_remote_user = $this->cosignglobalfuncs('getServerVar', 'REDIRECT_REMOTE_USER');
    $remote_user = $this->cosignglobalfuncs('getServerVar', 'REMOTE_USER');

    return self::cosignGetRemoteUser($redirect_remote_user, $remote_user);
  }

  /**
   * Helper function for cosignRetrieveRemoteUser.
   * Allows for easier testing.
   *
   * @return string
   *   String username or empty string.
   */
  public static function cosignGetRemoteUser($redirect_remote_user, $remote_user) {
    $cosign_name = '';
    // Make sure we get the remote user whichever way it is available.
    if (!empty($redirect_remote_user)) {
      $cosign_name = $redirect_remote_user;
    }
    elseif (!empty($remote_user)) {
      $cosign_name = $remote_user;
    }

    return $cosign_name;
  }

  /**
   * Attempts to retrieve the protossl from the $_SERVER variable.
   *
   * We need to check for https on logins.
   * We need to intercept redirects from routes and events.
   *
   * @return bool
   *   Boolean TRUE or FALSE.
   */
  public function cosignIsHttps() {
    return self::cosignCheckHttps($this->request->getCurrentRequest()->server->get('protossl'));
  }

  /**
   * Helper function for cosignIsHttps.
   * Allows for easier testing.
   *
   * @return bool
   *   Boolean TRUE or FALSE.
   */
  public static function cosignCheckHttps($request_protossl) {
    $is_https = FALSE;
    if ($request_protossl == 's') {
      $is_https = TRUE;
    }

    return $is_https;
  }

  /**
   * Attempts to retrieve the remote realm from the $_SERVER variable.
   *
   * If the user is logged in to cosign webserver auth,
   * the remote realm variable
   * will contain friend or UMICH.EDU (or some other implemetation).
   *
   * @return bool
   *   Boolean TRUE or FALSE.
   */
  public function cosignIsFriendAccount($username) {
    return self::cosignCheckFriendAccount($username, $this->cosignglobalfuncs('getServerVar', 'REMOTE_REALM'));
  }

  /**
   * Helper function for cosignIsFriendAccount.
   * Allows for easier testing.
   *
   * @return bool
   *   Boolean TRUE or FALSE.
   */
  public static function cosignCheckFriendAccount($username, $server_friend) {
    // Make sure we get friend whichever way it is available.
    $is_friend_account = FALSE;
    if ((isset($server_friend) && $server_friend == 'friend') || stristr($username, '@')) {
      $is_friend_account = TRUE;
    }

    return $is_friend_account;
  }
  
  /**
   * Create and login a Drupal user.
   *
   * @return bool
   *   Boolean TRUE or FALSE.
   */
  public function cosignCreateNewUser($cosign_name) {
    if ($autocreate = $this->cosignGetSettings('cosign_autocreate')) {
      $new_user = self::cosignNewUserVars($cosign_name, $this->cosignglobalfuncs('user_password'), $this->cosignGetSettings('cosignautocreate_email_domain'), $this->cosignIsFriendAccount($cosign_name));
      $user = User::create($new_user);
      $user->enforceIsNew();
      $user->save();
      $this->cosignLogInUser($user);
    }

    return $autocreate;
  }

  /**
   * Set New User Vars.
   * Helper function for cosignCreateNewUser.
   * Allows for easier testing.
   *
   * @return array
   *   Array().
   */
  public static function cosignNewUserVars($cosign_name, $pwd, $email, $is_friend = 0) {
    $new_user = [];
    $new_user['name'] = $cosign_name;
    $new_user['status'] = 1;
    $new_user['password'] = $pwd;
    $new_user['mail'] = $cosign_name . '@' . $email;
    if ($is_friend) {
      $new_user['mail'] = $cosign_name;
    }

    return $new_user;
  }

  /**
   * Get a cosign setting var.
   * Helper function to get cosign settings.
   * Allows for easier testing.
   *
   */
  private function cosignGetSettings($cosign_setting) {
    return $this->configFactory->get('cosign.settings')->get($cosign_setting);
  }

  /**
   * Helper function for drupal global functions.
   * Allows for easier testing.
   *
   */
  public function cosignglobalfuncs($func_name, $args = NULL) {
    switch ($func_name) {
      case 'user_login_finalize':
        return user_login_finalize($args);
        break;
      case 'user_logout':
        return user_logout();
        break;
      case 'user_password':
        return user_password();
        break;
      case 'getServerVar':
        if (isset($_SERVER[$args])) {
          return $_SERVER[$args];
        }
        break;
    }
    return NULL;
  }

  /**
   * User load helper function.
   * Allows for easier testing.
   *
   * @return \Drupal\user\Entity\User
   *   User object
   */
  public function cosignLoadUser($username) {
    if (is_integer($username)) {
      return User::load($username);
    }
    else {
      return user_load_by_name($username);
    }
  }

}
