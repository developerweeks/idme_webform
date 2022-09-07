<?php

namespace Drupal\idme_webform\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Url;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides an ID.me render element.
 *
 * In the annotation below we define the string "idmewidget" as the ID for this
 * plugin, which will be the value used for the '#type' property in a render
 * array.
 *
 * @RenderElement("idmewidget")
 */
class IDmeWidget extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      // Yes this element will hold a value.
      '#input' => TRUE,
      '#default_value' => NULL,
      '#sesh' => '',
      '#process' => [
        [$class, 'processIdme'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#element_validate' => [
        [$class, 'consolidate'],
      ],
    ];
  }

  /**
   * Processes the IDme form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   *
   * @throws \InvalidArgumentException
   *   Thrown when something is not right.
   */
  public static function processIdme(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (FALSE) {
      throw new \InvalidArgumentException('The #available_countries property must be an array.');
    }
    // This function runs during the form build process.
    $form_object = $form_state->getFormObject();
    // Widget only work for webform submissions right now.
    if (!$form_object instanceof WebformSubmissionForm) {
      $element['#access'] = FALSE;
      return $element;
    }

    $idme_config = \Drupal::service('config.factory')->getEditable('idme_webform.settings');
    $code = \Drupal::request()->query->get('code');
    $iDstarted = FALSE;

    // Build an element to hold the interaction.
    $element['idme'] = [
      '#id' => 'idme_wrapper',
      '#tree' => TRUE,
      '#type' => 'container',
      '#attributes' => [
        'class' => 'idme_webform',
      ],
    ];

    // Attach our styles and helper.
    $element['idme']['#attached']['library'][] = 'idme_webform/idmewidget';

    if (!empty($code)) {
      $element['#sesh'] = $code;
      \Drupal::logger('IDmeWidget')->notice('we have a code');
      $dupe = \Drupal::service('idme_webform.petard')->getRecord($code);
      if (FALSE === $dupe) {
        // We do not have a record of this code, attempt the handshake.
        \Drupal::service('idme_webform.petard')->startRecord($code);

        $pii_filter = $idme_config->get('pii_filter');
        // TODO: check the element settings for override.
        $data = \Drupal::service('idme_webform.petard')->handshake($code, $pii_filter);
        // Did we verify or did we fail?
        if (!empty($data)) {
          // Store our collected data.
          \Drupal::service('idme_webform.petard')->storeRecord($code, $data);

          $keep = 'Verified:';
          // The $data is already filtered with PII settings.
          foreach ($data as $key => $value) {
            // Attributes that start with "data-" can be referenced in the javascript.
            $element['idme']['#attributes']['data-' . $key] = $value;
            // Also compose our element value.
            $keep .= ' ' . $value;
          }
          \Drupal::logger('IDmeWidget')->notice('we assembled: ' . $keep);
          $greenmark = '/' . \Drupal::service('extension.list.module')->getPath('idme_webform') . '/images/idme-verified.png';
          $element['idme']['done'] = [
            '#markup' => '<img class="idmewidget" src="' . $greenmark . '"/>',
          ];
          $element['idme']['verified'] = [
            '#type' => 'hidden',
            '#value' => $keep,
          ];
          return $element;
        }
        // We have no data from the handshake.
        \Drupal::logger('IDmeWidget')->notice('Handshake returned empty. ' . $code);
        // No return line here, so code continues to the "first build" section and makes button.
      }
      else {
        // We have a record, in $dupe, let's use it.
        if ($dupe['verified']) {
          // The record is good.
          $keep = 'Verified:';
          // The $data is already filtered with PII settings.
          foreach ($dupe as $key => $value) {
            // Attributes that start with "data-" can be referenced in the javascript.
            $element['idme']['#attributes']['data-' . $key] = $value;
            // Also compose our element value.
            $keep .= ' ' . $value;
          }
          \Drupal::logger('IDmeWidget')->notice('we assembled: ' . $keep);
          $greenmark = '/' . \Drupal::service('extension.list.module')->getPath('idme_webform') . '/images/idme-verified.png';
          $element['idme']['done'] = [
            '#markup' => '<img class="idmewidget" src="' . $greenmark . '"/>',
          ];
          $element['idme']['verified'] = [
            '#type' => 'hidden',
            '#value' => $keep,
          ];
          return $element;
        }
        // No return line here, so code continues to the "first build" section and makes button.
      }
    }

    // First build of the form, make the button.
    $verify = '/' . \Drupal::service('extension.list.module')->getPath('idme_webform') . '/images/verify.svg';
    $callback_uri = \Drupal::request()->getSchemeAndHttpHost() . \Drupal::service('path.current')->getPath();
    // Build the authentication endpoint url.
    $aurl = $idme_config->get('idme_domain');
    $aurl .= '/oauth/authorize?client_id=' . $idme_config->get('client_id');
    $aurl .= '&redirect_uri=' . $callback_uri;
    $aurl .= '&response_type=code&scope=' . $idme_config->get('scope');

    // Access is used to change the visibility of this button if the person is verified.
    $element['idme']['link'] = [
      '#markup' => '<a class="idmewidget" href="' . $aurl . '"><img src="' . $verify . '" /></a>',
    ];
    return $element;
  }

  /**
   * Element validator actually used to pull the data from Petard.
   */
  public static function consolidate(&$element, FormStateInterface $form_state) {
    $element['#value'] = 'Unverified.';
    if (isset($element['idme']['verified']['#value'])) {
      $sesvi = $element['idme']['verified']['#value'];
      \Drupal::logger('IDmeWidget')->notice('found ' . $sesvi);
      $element['#value'] = $sesvi;
    }
    // Must save the value here too for it to stick.
    $form_state->setValue($element['#webform_key'], $element['#value']);

    // Now cleanup the Petard.
    $code = $element['#sesh'];
    \Drupal::service('idme_webform.petard')->purgeRecord($code);
    return TRUE;
  }

}
