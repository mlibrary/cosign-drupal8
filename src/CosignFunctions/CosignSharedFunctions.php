<?php

/**
 * @file
 * Contains Drupal\cosign\CosignFunctions\CosignSharedFunctions.
 */

namespace Drupal\cosign\CosignFunctions;

/**
 * Cosign shared functions.
 */
class CosignSharedFunctions {
  /**
   * Check whether user is loggedin to cosign, is a drupal user, and is logged into drupal
   *
   * @return
   *   User object
   */
  public static function cosign_user_status($cosign_username) {
    $user = \Drupal::currentUser();
    $uname = $user->getAccountName();
    $drupal_user = user_load_by_name($cosign_username);
    if ($drupal_user && $drupal_user->isBlocked()) {
      return null;
    }
    if (!empty($uname)) {
      //youre already logged in
      //make sure you are the cosign user. if not log out. This is unlikely
      if ($cosign_username != $uname) {
        user_logout();
        return null;
      }
    }
    if (!empty($cosign_username)){
      $is_friend_account = CosignSharedFunctions::cosign_is_friend_account($cosign_username);
      // If friend accounts are not allowed, log them out
      if (\Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts') == 0 && $is_friend_account) {
        CosignSharedFunctions::cosign_friend_not_allowed();
        if (\Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1){
          return user_load(0);
        }
        else {
          return null;
        }
      }
    }
    if (!empty($cosign_username) && !empty($drupal_user) && empty($uname)) {
      //login the cosign user
      CosignSharedFunctions::cosign_login_user($drupal_user);
    }
    elseif (!empty($cosign_username) && empty($drupal_user)) {
      //cosign user doesn't have a drupal account
      if (\Drupal::config('cosign.settings')->get('cosign_autocreate') == 1) {
        $new_user = CosignSharedFunctions::cosign_create_new_user($cosign_username);
        user_load($new_user->id(), TRUE);
      }
      else {
        //drupal_set_message(t('This site does not auto create users from cosign. Please contact the <a href="mailto:'. \Drupal::config("system.site")->get("mail").'">site administrator</a> to have an account created.'), 'warning');
        user_load(0);
      }
    }
    elseif (empty($cosign_username) && \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 0){
      //no cosign account found
      user_logout();
      return null;
    }
    $user = \Drupal::currentUser();
    if (!$user){
      $user = user_load(0);
    }
    if ($user->id() == 0 && \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1){
      //drupal_set_message(t('You do not have a valid cosign username. Browsing as anonymous user over https.'));
    }
    return $user;
  }

  /**
   * Logs cosign user into drupal
   *
   * @return
   *   User Object
   */
  public static function cosign_login_user($drupal_user) {
    user_login_finalize($drupal_user);
    $the_user = \Drupal::currentUser();
    $username = CosignSharedFunctions::cosign_retrieve_remote_user();
    if ($the_user->getAccountName() != $username) {
      \Drupal::logger('cosign')->notice('User attempted login and the cosign username: @remote_user, did not match the drupal username: @drupal_user', array('@remote_user' => $username, '@drupal_user' => $the_user->getAccountName()));
      user_logout();
    }

    return user_load($the_user->id(), TRUE);
  }

  /**
   * Performs tasks if friend accounts arent allowed
   *
   * @return
   *   null
   */
  public static function cosign_friend_not_allowed() {
    \Drupal::logger('cosign')->notice('User attempted login using a university friend account and the friend account configuration setting is turned off: @remote_user', array('@remote_user' => $username));
    drupal_set_message(t(\Drupal::config('cosign.settings')->get('cosign_friend_account_message')), 'warning');
    if (\Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https') == 1) {
      $cosign_brand = \Drupal::config('cosign.settings')->get('cosign_branded');
      drupal_set_message(t('You might want to <a href="/user/logout">logout of '.$cosign_brand.'</a> to browse anonymously or as another '.$cosign_brand.' user.'), 'warning');
    }
    else {
      user_logout();
      return null;
    }
  }
  
  public static function cosign_logout_url() {
    $logout_path = \Drupal::config('cosign.settings')->get('cosign_logout_path');
    $logout_to = \Drupal::config('cosign.settings')->get('cosign_logout_to').'/';
    return $logout_path . '?' . $logout_to;
  }

  /**
   * Attempts to retrieve the remote user from the $_SERVER variable.
   *
   * If the user is logged in to cosign webserver auth, the remote user variable
   * will contain the name of the user logged in.
   *
   * @return
   *   String username or empty string.
   */
  public static function cosign_retrieve_remote_user() {
    $cosign_name = '';
    // Make sure we get the remote user whichever way it is available.
    if (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
      $cosign_name = $_SERVER['REDIRECT_REMOTE_USER'];
    }
    elseif (isset($_SERVER['REMOTE_USER'])) {
      $cosign_name = $_SERVER['REMOTE_USER'];
    }

    return $cosign_name;
  }

  /**
   * Attempts to retrieve the protossl from the $_SERVER variable.
   *
   * We need to check for https on logins. 
   * since we need to intercept redirects from routes and events, this is a shared function
   *
   * @return
   *   Boolean TRUE or FALSE.
   */

  public static function cosign_is_https() {
    $is_https = FALSE;
    if (\Drupal::request()->server->get('protossl') == 's') {
      $is_https = TRUE;
    }

    return $is_https;
  }

  /**
   * Attempts to retrieve the remote realm from the $_SERVER variable.
   *
   * If the user is logged in to cosign webserver auth, the remote realm variable
   * will contain friend or UMICH.EDU (or some other implemetation).
   *
   * @return
   *   Boolean TRUE or FALSE.
   */
  public static function cosign_is_friend_account($username) {
    // Make sure we get friend whichever way it is available.
    $is_friend_account = FALSE;
    if ((isset($_SERVER['REMOTE_REALM']) && $_SERVER['REMOTE_REALM'] == 'friend') || stristr($username, '@')) {
      $is_friend_account = TRUE;
    }

    return $is_friend_account;
  }

  /**
   * Creates a new drupal user with the cosign username and email address with domain from admin form.
   *
   * @return
   *   Object user account.
   */
  public static function cosign_create_new_user($cosign_name){
    if (\Drupal::config('cosign.settings')->get('cosign_autocreate') == 1) {
      $new_user = array();
      $new_user['name'] = $cosign_name;
      $new_user['status'] = 1;
      $new_user['password'] = user_password();
      if (CosignSharedFunctions::cosign_is_friend_account($cosign_name)) {
        // friend account
        $new_user['mail'] = $cosign_name;
      }
      else{
        $new_user['mail'] = $cosign_name . '@' . \Drupal::config('cosign.settings')->get('cosignautocreate_email_domain');
      }
      $account = entity_create('user', $new_user);
      $account->enforceIsNew();
      $account->save();

      return CosignSharedFunctions::cosign_login_user($account);
    }
  }
}