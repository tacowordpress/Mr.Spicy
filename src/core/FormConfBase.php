<?php

add_action('init', '\Taco\MrSpicy::getSessionData');

class FormConfBase extends \Taco\Post {

  public function getFields() {
    return array(
      'unique_id' => ['type' => 'hidden'],
      'api_key' => ['type' => 'text'],
      'fields' => array('type' => 'hidden'),
      'form_description' => array('type' => 'textarea'),
      'admin_emails' => array('type' => 'text'),
      'success_redirect_url' => array(
        'type' => 'url',
        'description' => 'If this field is filled out, the user will not see the success message and instead will be directed to the url specified'
      ),
      'form_success_message' => array(
        'type' => 'textarea',
      ),
      'form_error_message' => array(
        'type' => 'textarea'
      ),
      'on_success' => array(
        'type' => 'hidden'
      ),
      'use_honeypot' => array(
        'type' => 'hidden'
      ),
      'honeypot_field_name' => array(
        'type' => 'hidden'
      ),
      'use_recaptcha' => array(
        'type' => 'hidden'
      ),
      'google_recaptcha_site_key' => array(
        'type' => 'hidden'
      ),
      'google_recaptcha_secret_key' => array(
        'type' => 'hidden'
      ),
      'g-recaptcha-response' => array(
        'type' => 'hidden'
      )
    );
  }

  public function getAPIKey() {
    if(!array_key_exists('post', $_GET)) {
      return md5(microtime().rand());
    }
    $api_key = get_post_meta($_GET['post'], 'api_key', true);

    if(strlen($api_key)) {
      return $api_key;
    }
    return md5(microtime().rand());
  }

  public function getRenderMetaBoxField($name, $field) {
    if($name === 'api_key') {
      $html = [];
      $html[] = sprintf('<input name="api_key" readonly="readonly" type="text" style="width: 100%%;" value="%s">', $this->getAPIKey());
      $html[] = '<br><br><span><strong>Developers:</strong> To use, copy and paste in your code.</span><br><br>';
      return join('', $html);
    }

    return parent::getRenderMetaBoxField($name, $field);
  }


  public function getPublic() {
    return false;
  }

  public function getExcludeFromSearch() {
    return true;
  }

  public function getAdminColumns() {
    return array('form_description', 'author');
  }
}
