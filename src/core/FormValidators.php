<?php

/* All of these methods are for checking a value for a certain condition(s)
 * Every method has the parameters "$value" and "property_value".
 * The $property_value allows for things like
 *   e.g. comparing string length against a maxlength (you name it)
 */

namespace Taco;

trait FormValidators {


  /**
   * check if a field is required
   * @param  $value string
   * @return $property_value string
   */
  public static function checkRequired($value, $property_value=null) {
    if(empty($value)) {
      return true;
    }
    return false;
  }


  /**
   * check if a field value is a valid email with "filter_var" and domain using "checkdnsrr"
   * @param  $value string
   * @return $property_value string
   */
  public static function checkEmail($value, $property_value=null) {
    if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
      return true;
    }
    $email_domain = array_pop(explode("@", $value));
    if(!checkdnsrr($email_domain, 'MX')) {
      return true;
    }

    return false;
  }


  /**
   * check if a field is a valid url
   * @param  $value string
   * @return $property_value string
   */
  public static function checkURL($value, $property_value=null) {
    if(!strlen($value)) return true;
    if(!filter_var($value, FILTER_VALIDATE_URL)) {
      return false;
    }
    return true;
  }


  /**
   * check if the honeypot field has a value
   * @param  $value string
   * @return $property_value string
   */
  public static function checkHoneyPot($value, $property_value=null) {
    if(strlen($value)) return true;
    return false;
  }


  /**
   * check if the value doesn't exceed a certain maxlength
   * @param  $value string
   * @return $property_value string
   */
  public static function checkMaxLength($value, $property_value=null) {
    if(strlen($value) > $property_value) return true;
    return false;
  }


  /**
   * check if a field is a valid U.S. zip
   * @param  $value string
   * @return $property_value string
   */
  public static function checkZip($value, $property_value=null) {
    $is_zip_regex = '^\d{5}([\-]?\d{4})?$';
    if(!preg_match("/".$is_zip_regex."/i", $value)) {
      return true;
    }
    return false;
  }


  /**
   * check if Google Recaptcha value is valid
   * @param  $value string
   * @return $property_value string
   */
  public function isGCaptchaFieldInValid($value, $property_value) {

    if(!strlen($value)) {
      return true;
    }
      $fields = array(
    	'secret' => $property_value,
    	'response' => $value
    );
    $fields_string = '';
    foreach($fields as $key => $value) {
      $fields_string .= $key.'='.$value.'&';
    }
    rtrim($fields_string, '&');

    $ch = curl_init();
    $timeout = 10;
    curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result);

    if($data->success != 1) {
      return true;
    }
    return false;
  }
}
