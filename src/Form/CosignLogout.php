<?php

namespace Drupal\cosign\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The cosign logout form.
 */
class CosignLogout extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cosign_logout_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['cosign_logout'] = [
      '#type' => 'button',
      '#value' => $this->t('Yes'),
      '#executes_submit_callback' => TRUE,
    ];
    $form['site_logout'] = [
      '#type' => 'button',
      '#value' => $this->t('No'),
      '#executes_submit_callback' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // none. just a redirect.
    echo "grr";
    exit($form_state->getTriggeringElement()['#parents'][0]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    echo "ugh";
    exit($form_state->getTriggeringElement()['#parents'][0]);
    if ($form_state->getTriggeringElement()['#parents'][0] == 'cosign_logout') {
      $form_state->setRedirectUrl(Url::fromRoute('cosign.cosignlogout'));
    }
    elseif ($form_state->getTriggeringElement()['#parents'][0] == 'site_logout') {
      user_logout();
      global $base_url;
      $form_state->setRedirectUrl(Url::fromUri($base_url));
    }
  }

}
