<?php

/**
 * @file
 * Contains \Drupal\cosign\Form\CosignAdmin.
 */

namespace Drupal\cosign\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class CosignAdmin extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cosign_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('cosign.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);

    //clear all routing caches to update any changed settings.
    \Drupal::service("router.builder")->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cosign.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $form['cosign_branded'] = [
      '#type' => 'textfield',
      '#title' => t('Brand Cosign'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_branded'),
      '#size' => 80,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#description' => t("Enter what you want Cosign to be called if your organization brands it differently."),
    ];

    $form['cosign_logout_path'] = [
      '#type' => 'textfield',
      '#title' => t('Logout Path'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_logout_path'),
      '#size' => 80,
      '#maxlength' => 200,
      '#description' => t("The address (including http(s)) of the machine and script path for logging out. Cosign has two options for logout. The default of the Cosign module is to use the local server logout script. This script immediately kills the local service cookie and redirects the user to central Cosign weblogin to logout. Alternatively, you can instead change the logout address to the central Cosign weblogin logout script (for the University of Michigan, it is: https://weblogin.umich.edu/cgi-bin/logout). <del>By redirecting the user directly to the central Cosign weblogin logout script, the user's service cookie will not be immediately destroyed. Instead, it will remain active for a period of up to 1 minute during which time the user's HTTP requests will remain authenticated by the local service cookie.</del> Cosign for Drupal 8 will now attempt to reset this cookie to jibberish during the logout process."),
    ];

    $form['cosign_logout_to'] = [
      '#type' => 'textfield',
      '#title' => t('Logout to'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_logout_to'),
      '#size' => 80,
      '#maxlength' => 200,
      '#description' => t("The address to redirect users to after they have logged out. Cosign also requires you add a trailing slash for a home address (ie http://google.com/), but not for a page address (ie http://google.com/page)"),
    ];

    $YesNo = [
      1 => 'Yes',
      0 => 'No',
    ];

    $form['cosign_allow_anons_on_https'] = [
      '#type' => 'select',
      '#title' => t('Allow anonymous users to browse over https?'),
      '#description' => t('If yes, users are not logged in automatically to drupal at an https address even if they are logged into cosign. If no, users are logged in to drupal through cosign after hitting an https address.<br><del>NOTE: this should probably be set to No at the University of Michigan until cosign does not force user logins for all https addresses. This also means the logout to address should be set to http.</del><br>ALSO NOTE: if your cosign installation does not force user logins at https and you set this to No, but your site does allow anonymous http browsing, users will be unable to access the content they have access to on http over https.'),
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_allow_anons_on_https'),
    ];

    $form['cosign_allow_cosign_anons'] = [
      '#type' => 'select',
      '#title' => t('Allow logged in cosign users to browse anonymously?'),
      '#description' => t('If Yes, logged in cosign users can browse the site anonymously by logging out of drupal. If No, logged in cosign users will be logged in automatically to drupal.'),
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_allow_cosign_anons'),
    ];

    $form['cosign_login_path'] = [
      '#type' => 'textfield',
      '#title' => t('Login Path'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_login_path'),
      '#size' => 80,
      '#maxlength' => 200,
      '#description' => t("The address (including http(s)) of the machine and script path for logging in to Cosign. The address should be everything before the query (at UM it is likely 'https://weblogin.umich.edu/'). This only has an efferct if allow anons is set to false and the cosign service does not do passive login (ie forcing cosign login on all https addresses). Note that this has no effect at the University of Michigan until anonymous browsing over https is available as all users are forced to login over https addresses."),
    ];
    //TODO this is not implemented, this always happens currently. not sure what the use case for keeping drupal users logged in
    $form['cosign_autologout'] = [
      '#type' => 'select',
      '#title' => t('Logout users from Drupal when their Cosign session expires?'),
      '#description' => t('If not selected, when users logout of Cosign, they will not also be automatically logged out of Drupal. This can lead to quite a bit of confusion depending on how you have Cosign configured on the server and how your site is setup. For instance, a user could logout of Cosign through a different Cosign protected application, return to the site, and access protected pages after thinking they\'ve already logged out. This can be a security issue if users think they\'re logged out of Drupal but are not.'),
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_autologout'),
    ];

    $form['cosign_autocreate'] = [
      '#type' => 'select',
      '#title' => 'Auto-create Users?',
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_autocreate'),
    ];

    $form['cosignautocreate_email_domain'] = [
      '#type' => 'textfield',
      '#title' => t('Email Domain for auto-generated users'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosignautocreate_email_domain'),
      '#size' => 80,
      '#maxlength' => 200,
      '#description' => t("This message is only relevant if you have 'Auto-create Users' set to 'Yes'. The default email domain for users generated by cosign"),
    ];
    //TODO currently, they get autocreated depending on friend status or anonymous user is returned. not sure we have a use case for this in the UM library at least...
    $form['cosign_invalid_login'] = [
      '#type' => 'textfield',
      '#title' => t('Redirect for users without a Drupal account'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_invalid_login'),
      '#size' => 80,
      '#maxlength' => 200,
      '#description' => t("The address or path where users should land if they authenticate through Cosign, but do not have a corresponding Drupal account, and thus are not logged in to Drupal. This is only relevant if you have 'Auto-create Users' set to 'No' or 'Allow friend accounts' set to 'No'. If you set this to an internal path that doesn't exist, you must create the page you set it to."),
    ];
    //TODO not implemented. see above
    $form['cosign_invalid_login_message'] = [
      '#type' => 'textarea',
      '#title' => t('Message displayed to users after they authenticate to Cosign but don\'t have a Drupal account'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_invalid_login_message'),
      '#description' => t("This message is only relevant if you have 'Auto-create Users' set to 'No'. When a user logs in who doesn't have an account, this message will tell them what happened."),
    ];

    $form['cosign_allow_friend_accounts'] = [
      '#type' => 'select',
      '#title' => 'Allow friend accounts?',
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_allow_friend_accounts'),
    ];

    $form['cosign_friend_account_message'] = [
      '#type' => 'textarea',
      '#title' => t('Message displayed to users after they authenticate to Cosign with a friend account'),
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_friend_account_message'),
      '#description' => t("This message is only relevant if you have 'Allow friend accounts' set to 'No'. When a friend account user logs in, this message will tell them that they don't have access."),
    ];

    $form['cosign_ban_password_resets'] = [
      '#type' => 'select',
      '#title' => t('Ban users access to the /user/password and /user/reset core functions'),
      '#description' => t('If Yes, users cannot change their drupal passwords.'),
      '#options' => $YesNo,
      '#default_value' => \Drupal::config('cosign.settings')->get('cosign_ban_password_resets'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('cosign_allow_anons_on_https') == 0 && $form_state->getValue('cosign_allow_cosign_anons') == 1) {
      $form_state->setErrorByName(
        'cosign_allow_anons_on_https',
        $this->t("Cosign users cannot browse anonymously if Anonymous users can't. Set Allow Anonymous Users to browse over https to Yes. OR")
      );
      $form_state->setErrorByName(
        'cosign_allow_cosign_anons',
        $this->t("Cosign users cannot browse anonymously if Anonymous users can't. Set Allow Cosign Users to browse anonymously to No.")
      );
    }
  }

}
