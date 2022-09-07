<?php

namespace Drupal\idme_webform;

/**
 * @file
 * Store, fetch, and decrypt the PII from ID.me.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides a tool for processing the ID.me transaction.
 */
class Petard {

  /**
   * This is the poper method of injecting a service dependency.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  // https://www.drupal.org/node/2270941
  // TODO: write class Get/Set/Save functions like normal OOP.

  /**
   * Constructs a new Petard object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Service injection of the database connection.
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  /**
   * Create a row for this hash key.
   */
  public function startRecord($hash) {
    // Should add a security check?  I'm touching the database.
    $dupe = $this->getRecord($hash);

    if (FALSE === $dupe) {
      // getRecord did not find a match for this hash.
      $database = \Drupal::database();
      $now = \Drupal::time()->getCurrentTime();

      $query = $database->insert('idme_petard')
        ->fields(['ptid', 'created'])
        ->values([$hash, $now]);
      $query->execute();
    }

    return;
  }

  /**
   * Static query check for the value.
   */
  public function getRecord($hash) {
    if (empty($hash)) {
      return FALSE;
    }
    // Should add a security check?
    $database = \Drupal::database();
    // Using static query. Each example has brackets on the {table} called.
    // Only pull the data pieces that we could get from ID.me.
    $result = $database->query("SELECT fname, lname, email, zip, age, birth_date, social, street, city, state, phone, verified FROM {idme_petard} WHERE ptid = :holder", [
      ':holder' => $hash,
    ]);

    if ($result === FALSE) {
      // No record was found.
      return FALSE;
    }

    // Return an associative array of columns and values.
    return $result->fetchAssoc();
  }

  /**
   * Add data to the record.
   */
  public function setData($hash, $field, $data) {
    // TODO: check if more security is needed before using db api.
    $database = \Drupal::database();
    $query = $database->update('idme_petard')
      ->condition('ptid', $hash)
      ->fields([$field => $data]);

    // The execute() method will return the number of rows affected by the query.
    return $query->execute();
  }

  /**
   * Store the chosen PII in the record.
   */
  public function storeRecord($hash, $attributes, $piif = NULL) {
    // Was getting an error of no fields included in update statement.
    $now = \Drupal::time()->getCurrentTime();
    $attributes['created'] = $now;
    $fields = $attributes;
    // Update: Controller is now processing filter, just go straight to storage.
    // That is probably not the best security choice.

    // Now store the selection.
    $database = \Drupal::database();
    $query = $database->update('idme_petard')
      ->fields($fields)
      ->condition('ptid', $hash)
      ->execute();
    // The execute() method will return the number of rows affected by the query.
    return $query;
  }

  /**
   * Trade code for token, and token for details.  Then filter those details according to config.
   */
  public function handshake($code = '', $pii_filter = []) {
    if (empty($code)) {
      \Drupal::logger('Petard')->error('Required Code query is empty.');
      // We need that code to get the token, cannot proceed.
      return FALSE;
    }
    \Drupal::logger('Petard')->notice('Handshake started with code ' . $code);

    $idme_config = \Drupal::service('config.factory')->getEditable('idme_webform.settings');
    // At this point, ID.me has triggered this URL with a ?code.
    $tokenPoint = $idme_config->get('idme_domain') . '/oauth/token';
    $clid = $idme_config->get('client_id');
    $clst = $idme_config->get('secret');
    $callback_uri = \Drupal::request()->getSchemeAndHttpHost() . \Drupal::service('path.current')->getPath();

    // Variable substitution happens directly in double quotes.
    $content = "code=$code&client_id=$clid&client_secret=$clst&redirect_uri=$callback_uri&grant_type=authorization_code";

    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $tokenPoint,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $content
    ]);
    $answer = curl_exec($curl);
    curl_close($curl);

    $token = json_decode($answer);
    if (!isset($token->access_token)) {
      \Drupal::logger('Petard')->error('Token not received. ' . $answer);
      // We need that token to continue, so this is a fail.
      return FALSE;
    }

    // We have done the second exchange with ID.me, and have a Token. Now we need to request attributes.
    $curl = curl_init();
    $theGoods = $idme_config->get('idme_domain') . '/api/public/v3/attributes.json?access_token=' . $token->access_token;
    curl_setopt_array($curl, [
      CURLOPT_URL => $theGoods,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_CUSTOMREQUEST => 'GET',
    ]);

    $received = curl_exec($curl);
    curl_close($curl);
    \Drupal::logger('Petard')->notice('Received encrypted package. ' . $received);
    // Now we need to decode the attributes we have received.
    $received = json_decode($received);

    // Everything comes over in base64 to dodge WAF.
    $crypted = base64_decode($received->data);
    $passed_key = base64_decode($received->key);
    $iv = base64_decode($received->iv);
    // Base64 decoded, now it cannot be copy-pasted from the browser.

    // By storing the secrets in config, they are not committed to git and not hardcoded.
    $certpass = $idme_config->get('certpass');
    $certhalf = $idme_config->get('certhalf');
    $encrypt_method = "AES-256-CBC";

    // Unlock our cert key.
    $res1 = openssl_get_privatekey($certhalf, $certpass);
    // Using the .pem instead of .key would let us skip that step.

    // Decrypts the encrypted session key using the Consumers private key.
    // This idme_key variable passed by reference, and filled in the function.
    $idme_key = '';
    $flag = openssl_private_decrypt($passed_key, $idme_key, $res1);
    if (!$flag) {
      \Drupal::logger('Petard')->error('Private decrypt failed.');
      return FALSE;
    }

    // Uses the decrypted session key and the IV to decrypt the data payload.
    $package = openssl_decrypt($crypted, $encrypt_method, $idme_key, OPENSSL_RAW_DATA, $iv);

    if (FALSE === $package) {
      \Drupal::logger('Petard')->error('Package decrypt failed.');
      return FALSE;
    }

    $package = json_decode($package);

    // Start our curated collection.
    $attribs = [
      'verified' => $package->status[0]->verified,
    ];

    // While we have config open, run through the PII filter.
    foreach ($package->attributes as $detail) {
      // If pii_filter is empty, everything will be discarded.
      if ($pii_filter[$detail->handle]) {
        // This is one of the PII details to keep.
        $attribs[$detail->handle] = $detail->value;
      }
    }
    return $attribs;
  }

  /**
   * Remove PII record matching this hash key from the database.
   */
  public function purgeRecord($hash) {
    // Should add a security check?  I'm touching the database.
    $database = \Drupal::database();
    $query = $database->delete('idme_petard')
      ->condition('ptid', $hash);

    // The execute() method will return the number of rows affected by the query.
    return $query->execute();
  }

  /**
   * Remove old PII records from the database.
   */
  public function outdated() {
    // Should add a security check?  I'm touching the database.
    $database = \Drupal::database();
    // Get timestamp for 1 hour ago.
    $gate = \Drupal::time()->getCurrentTime() - 3600;
    $query = $database->delete('idme_petard')
      ->condition('created', $gate, '<');

    // The execute() method will return the number of rows affected by the query.
    return $query->execute();
  }

}
