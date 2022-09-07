<?php

namespace Drupal\idme_webform\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to configure Search Adds settings.
 */
class IDmeSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'idme_webform_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'idme_webform.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('idme_webform.settings');

    $form['toptext'] = [
      '#type' => 'markup',
      '#markup' => '<p>Here is where account details and such are set.  These settings are used in all ID.me interactions.</p>',
    ];

    // Fieldnames need to be unique for form value handling.
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDme Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Client ID for api calls'),
      '#required' => TRUE,
    ];
    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDme Client Secret'),
      '#default_value' => $config->get('secret'),
      '#description' => $this->t('IDme client secret used in encryptions'),
      '#required' => TRUE,
    ];

    $iddoma = $config->get('idme_domain');
    $form['idme_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDme Domain'),
      '#default_value' => $iddoma,
      '#description' => "Base of url for calls to ID.me<br> - Token url $iddoma/oauth/token<br> - Authorization Endpoint $iddoma/oauth/authorize<br> - Attributes URL $iddoma/api/public/v3/attributes.json",
      '#required' => TRUE,
    ];

    $form['pii_filter'] = [
      '#type' => 'checkboxes',
      '#options' => [
        'fname' => $this->t('First name'),
        'lname' => $this->t('Last name'),
        'email' => $this->t('Email'),
        'zip' => $this->t('Zip code'),
        'age' => $this->t('Age'),
        'birth_date' => $this->t('Date of Birth'),
        'social' => $this->t('Social Security number'),
        'street' => $this->t('Street Address'),
        'city' => $this->t('City'),
        'state' => $this->t('State'),
        'phone' => $this->t('Phone number'),
      ],
      '#title' => $this->t('What identifying data to listen for?'),
      '#default_value' => $config->get('pii_filter'),
      // Default value is an array that looks like this?
      // @"l":"l","d":"d","f":0,"s":0.
    ];
    $form['scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDme Scope'),
      '#default_value' => $config->get('scope'),
      '#description' => $this->t(''),
      '#required' => TRUE,
    ];
    // Decryption pieces.
    $form['certpass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unlock our cert'),
      '#default_value' => $config->get('certpass'),
      '#description' => $this->t(''),
      '#required' => TRUE,
    ];
    $form['certhalf'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Our larger secret'),
      '#default_value' => $config->get('certhalf'),
      '#description' => $this->t(''),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Save the configuration changes.
    $idme_config = \Drupal::service('config.factory')->getEditable('idme_webform.settings');
    $idme_config->set('client_id', $values['client_id']);
    $idme_config->set('idme_domain', $values['idme_domain']);
    $idme_config->set('pii_filter', $values['pii_filter']);
    $idme_config->set('secret', $values['secret']);
    $idme_config->set('scope', $values['scope']);
    $idme_config->set('certpass', $values['certpass']);
    $idme_config->set('certhalf', $values['certhalf']);
    // Values dont stick until they are saved.
    $idme_config->save();

    parent::submitForm($form, $form_state);
  }

}
