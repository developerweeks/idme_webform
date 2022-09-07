<?php

namespace Drupal\idme_webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Plugin\WebformElement\WebformDisplayOnTrait;
use Drupal\webform\Plugin\WebformElementAttachmentInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\Plugin\WebformElementDisplayOnInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a widget element for using ID.me api.
 *
 * @WebformElement(
 *   id = "idmewidget",
 *   label = @Translation("IDme Widget"),
 *   description = @Translation("Generates a linked button."),
 *   category = @Translation("Advanced elements"),
 * )
 */
class IDmeWidget extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Remove this section that does not apply.
    unset($form['element_description']);

    // Add these options right next to field title.
    $form['element']['pii_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override global PII Settings'),
      '#access' => TRUE,
    ];

    $form['element']['pii_choice'] = [
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
      '#title' => $this->t('What identifying data to record.'),
      '#access' => TRUE,
      '#states' => [
        'visible' => [
          [':input[name="pii_override"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    return $form;
  }

  /**
   * The webform submission (server-side) conditions (#states) validator.
   *
   * @var \Drupal\webform\WebformSubmissionConditionsValidator
   */
  protected $conditionsValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->conditionsValidator = $container->get('webform_submission.conditions_validator');
    return $instance;
    // I am unclear on this function's purpose - Mike.
    // Is this where I pull the config to forward API key to template?
    /** @var \Drupal\Core\Config\Config $config */
    // $config = \Drupal::service('config.factory')->get('idme_webform.settings');
    // '%value' => $config->get('global_counter'),

  }

  /**
   * {@inheritdoc}
   */
  public function hasValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(array &$element, WebformSubmissionInterface $webform_submission) {
    // Here is where I can format the PII coming back from ID.me.
    $key = $element['#webform_key'];
    $data = $webform_submission->getData();
    // Make sure attachment element never stores a value.
    if (isset($data[$key])) {
      // The value has been set on this element.
      // Todo: decide if we want to move parent::consolidate() code into this function instead.
      // $webform_submission->setData($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    // ID.me elements should never get a test value.
    return NULL;
  }

}
