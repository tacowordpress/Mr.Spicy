<?php

// An API for the FormConf class

namespace Taco;

class MrSpicy {
  use \Taco\FormValidators;

  private $settings = [];
  private static $defaults_path = null;
  private static $submit_action_uri = '';

  public static $invalid = false;
  public static $success = false;
  public static $session_field_errors = array();
  public static $session_field_values = array();
  public static $entry_id;

  public $fields = null;
  public $template_html = null;
  public $conf_instance = null;
  public $conf_machine_name = null;
  public $conf_ID = null;

  public static $messages_reference = array(
    'form_conf_invalid',
    'form_conf_success'
  );

  /**
   * creates a new MrSpicy and associated configuration
   * @param  $args array
   * @param  $template_callback callable
   * @return MrSpicy object
   */
  public function __construct($args, $template_callback=null) {
    // set submit action uri
    self::setSubmitActionURI();

    $defaults = array(
      'form_unique_key' => null,
      'fields' => array(),
      'css_class' => '',
      'id' => '',
      'method' => 'post',
      'action' => null,
      'novalidate' => false,
      // 'use_ajax' => false, coming soon
      'hide_labels' => false,
      'column_classes' => 'small-12 columns',
      'exclude_post_content' => false,
      // 'use_cache' => false, // coming soon
      'lock' => false, // prevent saving dev settings
      'submit_button_text' => 'submit',
      'success_message' => null,
      'error_message' => null,
      'success_redirect_url' => null,
      'label_field_wrapper' => '\Taco\MrSpicy::rowColumnWrap',
      'on_success' => null, // on success event callback,
      'use_honeypot' => false,
      'honeypot_field_name' => 'your_webite_url',
      'test_with_fakes' => false, // coming soon,
      'use_recaptcha' => false,
      'google_recaptcha_site_key' => null,
      'google_recaptcha_secret_key' => null
    );

    // we need this to uniquely identify the form conf that will get created or loaded
    if(!(array_key_exists('form_unique_key', $args)
      && strlen($args['form_unique_key']))) {
        throw new \Exception('"form_unique_key" must be defined in the args array');
        exit;
    }

    // if the form configuration exists, load it
    $db_conf = $this->findFormConfigInstance($args['form_unique_key']);
    if(!$db_conf) {
      throw new \Exception('The Form\'s unique key is invalid.');
      exit;
    }
    $this->conf_instance = $db_conf;

    $conf_fields = $this->conf_instance->getFields();

    // load global defaults
    // TODO: find a better way than using a global
    global $mr_spicy_forms_defaults_path;
    if(strlen($mr_spicy_forms_defaults_path)) {
      $global_defaults = include $mr_spicy_forms_defaults_path;
    } else {
      $global_defaults = include __DIR__.'/../forms-defaults.php';
    }

    // get the default form action
    $defaults['action'] = (array_key_exists('form_action', $global_defaults) && !is_null($global_defaults['form_action']))
      ? $global_defaults['form_action']
      : self::getSubmitActionURI();

    // assign only the fields specified above and in the form conf
    foreach($args as $k => $v) {
      if($k === 'fields' && $args['fields'] !== 'auto') {
        $this->fields = $v;
        continue;
      }
      if($k == 'on_success') continue;

      if(array_key_exists($k, $conf_fields)) {
        $this->conf_instance->set($args[$k], $args[$k]);
      }
      if(array_key_exists($k, $defaults)) {
        $this->settings[$k] = $args[$k];
      }
    }

    if(array_key_exists('lock', $args)) {
      $this->settings['lock'] = $args['lock'];
    }


    /* We cannot use a closure with an event callback
     *  where a redirect url is defined
     * Instead we must use a string
     */
    if(
      !is_object($this->settings['on_success'])
      && strlen($this->settings['on_success'])
      && strlen($this->settings['success_redirect_url'])
    ) {
      $this->conf_instance->set(
        'on_success',
        $this->settings['on_success']
      );
    } else {
      $this->conf_instance->set(
        'on_success',
        null
      );
      if(!strlen($this->conf_instance->get('success_redirect_url'))) {
        $this->conf_instance->set(
          'success_redirect_url',
          null
        );
      }
    }

    // if a callback is defined call it on success
    if(self::$success === true) {
      if($args['on_success']
        && !strlen($this->settings['success_redirect_url'])) {
        $taco_object = \Taco\Post::find(self::$entry_id);
        if(is_string($args['on_success'])) {
          // is it a string?
          $class_and_method = explode('::', $args['on_success']);
          $method_class = $class_and_method[0];
          $class_method = $class_and_method[1];
          $method_class::$class_method($taco_object, $this);
        } else {
          // it's a closure
          $args['on_success']($taco_object, $this);
        }
      }
    }

    // --- messages ---

    // first get global default messages
    $defaults['success_message'] = $global_defaults['success_message'];
    $defaults['error_message'] = $global_defaults['error_message'];

    // second get developer's hardcoded per form settings from $args
    if(array_key_exists('success_message', $this->settings)) {
      $defaults['success_message'] = $this->settings['success_message'];
    }

    if(array_key_exists('error_message', $this->settings)) {
      $defaults['error_message'] = $this->settings['error_message'];
    }

    // lastly use the WordPress admin's message settings
    if(strlen($this->conf_instance->get('form_success_message'))) {
      $this->settings['success_message'] = $this->conf_instance->get(
        'form_success_message'
      );
    }
    if(strlen($this->conf_instance->get('form_error_message'))) {
      $this->settings['error_message'] = $this->conf_instance->get(
        'form_error_message'
      );
    }


    // merge default settings with user settings
    $this->settings = array_merge(
      $defaults,
      $this->settings
    );


    // wrapper label/field method (is it the default, and is it a method or func?)
    if(is_string($this->settings['label_field_wrapper'])) {
      $wrapper_callable = explode(
        '::', $this->settings['label_field_wrapper']
      );
      if(count($wrapper_method) > 1) {
        $wrapper_callable = current($wrapper_callable);
      }
      $this->settings['label_field_wrapper'] = $wrapper_callable;
    }

    if($this->settings['fields'] !== 'auto') {
      $this->conf_instance->set('fields', serialize($this->fields));
    }

    // don't use a closure if the success_redirect_url is used
    if(strlen($this->settings['success_redirect_url'])
      && is_string($args['on_success'])) {
      $this->conf_instance->set(
        'on_success',
        $args['on_success']
      );
    }

    // throw an error if settings includes a success_redirect_url with a closure
    if(!is_string($args['on_success'])
      && strlen($this->settings['success_redirect_url'])) {
        throw new Exception(
          'MrSpicy: If you are using "success_redirect_url", you cannot use a closure.
          Use must specifiy valid string callback e.g. "MyClass::myMethod"',
          1
        );
    }

    $this->conf_instance->assign([
      'use_honeypot' => $this->settings['use_honeypot'],
      'honeypot_field_name' => $this->settings['honeypot_field_name']
    ]);

    $this->conf_instance->set(
      'use_recaptcha',
      $this->settings['use_recaptcha']
    );

    $this->conf_instance->set(
      'google_recaptcha_site_key',
      $this->settings['google_recaptcha_site_key']
    );

    $this->conf_instance->set(
      'google_recaptcha_secret_key',
      $this->settings['google_recaptcha_secret_key']
    );

    $this->conf_instance->set(
      'post_name',
      $this->conf_machine_name
    );

    // assign redirect url from dev's settings
    // if the admin adds this value, it will need to be overridden from wp-admin
    if(!strlen($this->conf_instance->get('success_redirect_url'))) {
      $this->conf_instance->set(
        'success_redirect_url',
        $this->settings['success_redirect_url']
      );
    }
 
    /* Do not save settings if locked (prevents extra db/backend work)
     * Checking for the prod environment could
     *  be one way of automatically turning the lock on or off
     */

  
    if($this->settings['lock'] == false) {
      // if the entry doesn't exist create it in the db
      $this->conf_ID = $this->conf_instance->save();
      // get the updated form conf after save
      $this->conf_instance = \FormConfig::find($this->conf_ID);
    } else {
      $temp_conf = $this->findFormConfigInstance($args['form_unique_key']);
      $this->conf_ID = $temp_conf->ID;
    }
    return $this;
  }


  /**
   * get property values from the settings array
   * @return string
   */
  public function __get($key) {
    return $this->get($key);
  }


  /**
   * get property values from the settings array
   * @return string
   */
  public function get($key) {
    if(array_key_exists($key, $this->settings)) {
      return $this->settings[$key];
    }
    return;
  }


  /**
   * find a form conf taco object in the db
   * @return $this
   */
  private function findFormConfigInstance($form_unique_key) {
    $db_instance = \FormConfig::getOneBy(
      'form_unique_key', $form_unique_key
    );
    if(\AppLibrary\Obj::iterable($db_instance)) {
      return $db_instance;
    }
    return false;
  }


  /**
   * automatically generate a fields/attribs from template tags
   * @param $callback callable
   * @return callable
   */
  public function getFieldsAutomatically($callback) {
    if(!($callback !== null && is_callable($callback))) {
      throw new Exception('"$callback" must be a valid callback');
    }

    $html_template = null;

    ob_start();
      $callback($this->conf_instance);
    $html_template = ob_get_clean();

    preg_match_all('/\%(.*)\%/', $html_template, $parts);
    $parts = $parts[1];

    $fields_raw = [];

    foreach($parts as $part) {
      $arg_key_values = [];
      if(preg_match('/\|/', $part)) {
        $part_args = explode('|', $part);
        $key = current($part_args);
        $part_args = array_slice($part_args, 1);
        foreach($part_args as $arg) {
          $keyvalues = explode('=', $arg);
          $arg_key_values[current($keyvalues)] = end($keyvalues);
        }
      } else {
        $key = $part;
        $part_args = [];
      }

      if(in_array($key, array('form_messages','post_content', 'edit_link'))) continue;
      if(in_array($key, array('post_content', 'edit_link', 'recaptcha'))) continue;
      $fields_raw[$key] = $arg_key_values;
      if(isset($key) && !array_key_exists('type', $fields_raw[$key])) {
        $fields_raw[$key]['type'] = 'text';
      }
    }
    $this->fields = $fields_raw;
    $this->conf_instance->set('fields', serialize($fields_raw));
    if($this->settings['lock'] == false) {
      $this->conf_instance->save();    
    }

    return $this->convertToPropperTemplate(
      $html_template
    );
  }


  /**
   * convert an html template that has fields with args to just fields keys
   * @param $html_template string
   * @return callable
   */
  public function convertToPropperTemplate($html_template) {

    preg_match_all('/\%(.*)%/', $html_template, $originals);
    preg_match_all('/\%([a-z_]*)/m', $html_template, $replacements);
    $replacements = array_values(
      array_filter($replacements[1])
    );
    $originals = $originals[0];

    $new_html = $html_template;
    $inc = 0;
    foreach($originals as $o) {
      $new_html = str_replace(
        $o,
        '%'.$replacements[$inc].'%',
        $new_html
      );
      $inc++;
    }
    return function() use ($new_html) {
      echo $new_html;
    };
  }


  /**
   * renders the form head
   * @return string html
   */
  public function render($callback=null) {
    if($this->get('fields') == 'auto') {
      $callback = $this->getFieldsAutomatically($callback);
    }
    if($callback !== null && is_callable($callback)) {
      return $this->renderCustom($callback, self::$session_field_values);
    }
    $html = [];
    $html[] = $this->renderFormHead();
    $html[] = $this->renderFormFields();
    $html[] = $this->renderFormFooter();
    return join('', $html);
  }


  /**
   * renders the form head
   * @return string html
   */
  public function renderFormHead($using_custom=false) {

    // start the form html using an array
    $html = [];

    $form_status = 'idle';
    if(self::$success) {
      $form_status = 'success';
    }
    if(self::$invalid) {
      $form_status = 'has_errors';
    }

    $html[] = sprintf(
      '<form action="%s" data-form-status="%s" method="%s" class="%s" id="%s" data-use-ajax="%s" %s>',
      $this->settings['action'],
      $form_status,
      $this->settings['method'],
      $this->settings['css_class']. ' mrspicy-forms',
      $this->settings['id'],
      $this->settings['use_ajax'],
      ($this->settings['novalidate']) ? 'novalidate' : ''
    );

    if($this->settings['use_recaptcha']) {
      $html[] = '<script src="https://www.google.com/recaptcha/api.js"></script>';
    }

    // get neccessary fields CSRF protection
    $form_entry_helper = new \FormEntry;
    $html[] = $form_entry_helper->getRenderPublicField('nonce');
    $html[] = $form_entry_helper->getRenderPublicField('class');
    $html[] = sprintf(
      '<input name="form_config" type="hidden" value="%d">',
      $this->conf_ID
    );
    $html[] = '<input name="mrspicy_form_submission" type="hidden" value="true">';

    if($this->settings['use_ajax']) {
      $html[] = '<input type="hidden" name="use_ajax" value="1">';
    }

    if($this->settings['use_honeypot']) {
      $html[] = $this->renderHoneyPotField();
    }

    // wrap with row and columns (foundation)
    if(!$this->settings['exclude_post_content'] && !$using_custom) {
      $html[] = $this->settings['label_field_wrapper'](
        $this->conf_instance->getTheContent(),
        $this->settings['column_classes']
      );
    }

    $messages = [];
    if(strlen($this->get('error_message'))) {
      $messages['error_message'] = $this->get('error_message');
    }

    //get form messages
    if(!$using_custom) {
      $html[] = $this->settings['label_field_wrapper'](
        $this->getFormMessages($messages),
        $this->settings['column_classes'].' form-messages'
      );
      $this->renderMessages();
    }

    return join('', $html);
  }


  public function renderMessages() {
    $messages = [];
    // check for success and error message overrides
    if(strlen($this->get('success_message'))) {
      $messages['success_message'] = $this->get('success_message');
    }
    if(strlen($this->get('error_message'))) {
      $messages['error_message'] = $this->get('error_message');
    }
    return $this->getFormMessages($messages);
  }


  /**
   * renders the form fields
   * @return string html
   */
  public function renderFormFields($return_as_array=false) {

    $html = [];
    foreach($this->fields as $k => $v) {

      if(array_key_exists('id', $v)) {
        $id = $v['id'];
      } else {
        $id = \AppLibrary\Str::machine($k, '-');
        $v['id'] = $id;
      }


      // does this field have an error
      $has_error = self::hasError($k);
      $error_columns_class = ($has_error)
        ? 'small-12 columns mrspicy-field-error' :
        'small-12 columns';

      // get the value if it exists
      $v['value'] = (self::getSessionFieldValue($k)) ?
        self::getSessionFieldValue($k)
        : '';

      if(array_key_exists('type', $v) && $v['type'] === 'checkbox') {
        $html[$k] = $this->settings['label_field_wrapper'](
          $this->renderCheckBox($k, $v),
          $error_columns_class,
          $k
        );
        continue;
      }

      $label = self::getLabelHTML($k, $v);

      if($this->get('hide_labels')
        && !array_key_exists('placeholder', $v)) {
        $v['placeholder'] = \AppLibrary\Str::human($k);
      }

      if(array_key_exists('label', $v) && $v['label'] == 'false') {
        $label = '';
      }

      $html[$k] = $this->settings['label_field_wrapper'](
        self::renderFieldErrors($k)
        .' '.$label.' '
        .$this->conf_instance->getRenderPublicField($k, $v),
        $error_columns_class,
        $k
      );
    }
    return (!$return_as_array)
      ? join('', $html)
      : $html;
  }


  /**
   * Get the HTML for a label
   * @param $key string
   * @param $field_attribs array
   * @return HTML
   */
  public function getLabelHTML($key, $field_attribs) {
    $hidden_class = ($this->hide_labels)
      ? 'hide_label'
      : '';

    $label_string = (array_key_exists('label', $field_attribs))
      ? $field_attribs['label']
      : $key;

    $required = '';
    if(array_key_exists('required', $field_attribs)) {
      $required = '<span>*</span>';
    }
    return sprintf(
      '<label for="%s" class="%s">%s %s</label>',
      $id,
      $hidden_class,
      \AppLibrary\Str::human($label_string),
      $required
    );
  }


  /**
   * renders a field's errors inline
   * @param $key string
   * @return string html
   */
  public function renderFieldErrors($key) {
    if(array_key_exists($key, self::$session_field_errors) && strlen(self::$session_field_errors[$key])) {
      return sprintf(
        '<span class="mrspicy-field-error-message">%s</span>',
        self::$session_field_errors[$key]
      );
    }
    return '';
  }


  /**
   * render a honey pot field
   * @return string
   */
  private function renderHoneyPotField() {
    return sprintf(
      '<style>input[name="%s"]{display:none;}</style><input type="text" name="%s">',
      $this->settings['honeypot_field_name'],
      $this->settings['honeypot_field_name']
    );
  }


  /**
   * render a Google Recaptcha
   * @return string
   */
  private function renderGoogleRecaptcha() {
    return sprintf(
      '<div class="g-recaptcha" data-sitekey="%s"></div>',
      $this->settings['google_recaptcha_site_key']
    );
  }


  /**
   * Get an array of field key => value (specifically for custom rendering of values)
   * @param $field_values array
   * @return array
   */
  private function getFieldValuesOfCustomRendered($field_values) {
    $fields = [];
    foreach($this->fields as $k => $v) {
      $value = (array_key_exists($k, $field_values))
        ? $field_values[$k]
        : '';
      $fields['value_'.$k.''] = $value;
    }
    return $fields;
  }


  /**
   * Get an array of field key => error (specifically for custom rendering of errors)
   * @return array
   */
  private function getFieldErrorsOfCustomRendered() {
    $fields = [];
    foreach($this->fields as $k => $v) {
      $fields['error_'.$k.''] = self::renderFieldErrors($k);
      $fields['class_field_error_'.$k.''] = (self::renderFieldErrors($k)) ? 'mrspicy-field-error' : '';
    }
    return $fields;
  }


  /**
   * get a custom form rendering defined by an html callback
   * @param $callback callable
   * @return boolean
   */
  private function renderCustom($callback, $field_values) {

    $html = [];
    $html[] = $this->renderFormHead(true);
    $rendered_fields = $this->renderFormFields(true);

    // add other useful content
    $rendered_fields['post_content'] = $this->conf_instance->getTheContent();
    $rendered_fields['edit_link'] = $this->renderFormEditLink();
    $rendered_fields['form_messages'] = $this->renderMessages();
    if($this->settings['use_recaptcha']) {
      $rendered_fields['recaptcha'] = $this->renderGoogleRecaptcha();
    }
    $rendered_fields = array_merge(
      $rendered_fields,
      $this->getFieldValuesOfCustomRendered($field_values),
      $this->getFieldErrorsOfCustomRendered()
    );

    // render the custom template
    \Taco\FormTemplate::create(
      array($rendered_fields),
      $callback,
      $rendered_template, // by reference
      $this->conf_instance
    );

    $html[] = $rendered_template;
    $html[] = '</form>';
    return join('', $html);
  }


  /**
   * does a field error exist
   * @param $key string
   * @return boolean
   */
  public function hasError($key) {
    if(array_key_exists($key, self::$session_field_errors)) {
      return true;
    }
    return false;
  }


  /**
   * renders a checkbox with label wraped around it
   * @param $key string
   * @param $value array
   * @return string html
   */
  public function renderCheckBox($key, $value) {
    $html = [];
    $html[] = sprintf(
      '<label for="%s">%s <span>%s</span></label>',
      \AppLibrary\Str::machine($key, '-'),
      $this->conf_instance->getRenderPublicField($key, $value),
      \AppLibrary\Str::human($key)
    );
    return join('', $html);
  }


  /**
   * renders the form footer (includes submit button)
   * @return string html
   */
  public function renderFormFooter() {
    $html = [];
    $html[] = $this->settings['label_field_wrapper'](sprintf(
      '<button type="submit">%s</button>',
      $this->get('submit_button_text')
    ));
    $html[] = $this->renderFormEditLink();
    $html[] = '</form>';
    return join('', $html);
  }


  /**
   * renders the the form edit link
   * @return string html
   */
  public function renderFormEditLink() {
    if(is_user_logged_in() && is_super_admin()) {
      return $this->settings['label_field_wrapper'](
        sprintf('<a href="/wp-admin/post.php?post=%d&action=edit">Edit this form\'s settings</a>',
          $this->conf_instance->ID
        )
      );
    }
    return '';
  }


  /**
   * gets any messages for the form like general errors or success messages
   * @return string html
   */
  public function getFormMessages($messages) {
    if(self::$invalid) {
      if(array_key_exists('error_message', $messages)
        && strlen($messages['error_message'])) {
        return $messages['error_message'];
      }
      return $this->get('form_error_message');
    }
    if(self::$success) {
      if(array_key_exists('success_message', $messages)
        && strlen($messages['success_message'])) {
        return $messages['success_message'];
      }
      return $this->get('form_success_message');
    }
  }


  /**
   * wraps a string in a foundation row + columns
   * @param $field string
   * @param $column_classes string of the classes that can be passed in
   * @return string html
   */
  public static function rowColumnWrap($field, $column_classes='small-12 columns', $field_key=null) {
    return sprintf(
      '<div class="row"><div class="%s">%s</div></div>',
      $column_classes,
      $field
    );
  }


  /**
   * validate the form
   * @return boolean
   */
  public static function validate($source_fields, $form_config) {
    if(array_key_exists('form_config', $source_fields)) {
      unset($source_fields['form_config']);
    }
    $invalid_array = [];
    $fields = unserialize(unserialize($form_config->get('fields')));

    if(
      $form_config->get('use_honeypot')
      && array_key_exists($form_config->get('honeypot_field_name'), $_POST)
    ) {
      $fields['honeypot'] = [];
      $source_fields['honeypot'] = $_POST[$form_config->get('honeypot_field_name')];
    }

    if(
      $form_config->get('use_recaptcha')
      && array_key_exists('g-recaptcha-response', $_POST)
    ) {
      $fields['recaptcha'] = array('type' => 'hidden');
      $source_fields['recaptcha'] = $_POST['g-recaptcha-response'];
    }

    foreach($fields as $k => $v) {
      $validation_types  = [];

      if(array_key_exists($k, $source_fields)) {
        $source_value = $source_fields[$k];
      } else {
        $source_value = null;
      }

      // $validation_types[string] where string is the method name
      // of the trait method in FormValidators.php
      if(array_key_exists('required', $v)) {
        $validation_types['checkRequired'] = true;
      }
      if(array_key_exists('type', $v) && $v['type'] === 'email') {
        $validation_types['checkEmail'] = 1;
      }
      if(array_key_exists('type', $v) && $v['type'] === 'url') {
        $validation_types['checkURL'] = 1;
      }
      if(array_key_exists('maxlength', $v)) {
        $validation_types['checkMaxLength'] = $v['maxlength'];
      }
      if($form_config->get('use_honeypot') && $k === 'honeypot') {
        $validation_types['checkHoneyPot'] = 1;
      }
      if($form_config->get('use_recaptcha') && $k == 'recaptcha') {
        $validation_types['isGCaptchaFieldInValid'] = $form_config->get('google_recaptcha_secret_key');
      }

      if(\AppLibrary\Arr::iterable($validation_types)) {
        list($invalid, $errors) = self::validateFieldRequirements(
          $validation_types,
          $source_value,
          $k
        );
        if($invalid) {
          $invalid_array[] = true;
        }
        self::pushErrors($k, join(', ', $errors)); // field key, $errors
        unset($errors);
        unset($validation_types);
      }
    }
    if(in_array(true, $invalid_array)) {
      self::setValuesIfErrorsExist($source_fields);
      self::setInvalid();
      return false;
    }
    self::setSuccess();
    return true;
  }


  /**
   * push errors
   * @param $key string
   * @param $errors array
   * @return void
   */
  public static function pushErrors($key, $errors) {
    session_start();
    if(is_array($errors)) {
      $errors = join(', ', $errors);
    }
    if(!array_key_exists('session_field_errors', $_SESSION)) {
      $_SESSION['session_field_errors'] = [];
    }
    $_SESSION['session_field_errors'][$key] = $errors;
    session_write_close();
  }


  /**
   * check all field requirements
   * @param $types array of requirements
   * @param $value string
   * @return array (boolean, array(error1, error2...))
   */
  public static function validateFieldRequirements($types, $value, $key) {
    $invalid_array = [];
    $errors = [];

    foreach($types as $method_name => $property_value) {
      $bool = self::$method_name(
        $value,
        $property_value
      );
      if($bool) {
        $invalid_array[] = true;
      }
    }
    if(in_array(true, $invalid_array)) {
      $invalid = true;
      $errors[] = sprintf(
        '%s is invalid',
        \AppLibrary\Str::human($key)
      );
    }
    return array($invalid, $errors);
  }


  /**
   * gets session messages and cache it in static class vars
   * @return void
   */
  public static function getSessionData() {
    session_start();

    if(array_key_exists('form_conf_invalid', $_SESSION)
      && $_SESSION['form_conf_invalid']) {
      self::$invalid = true;
      $_SESSION['form_conf_invalid'] = false;
    }
    if(array_key_exists('form_conf_success', $_SESSION)
      && $_SESSION['form_conf_success']) {
      self::$success = true;
      $_SESSION['form_conf_success'] = false;
    }
    if(array_key_exists('entry_id', $_SESSION)) {
      self::$entry_id = $_SESSION['entry_id'];
      unset($_SESSION['entry_id']);
    }
    if(array_key_exists('session_field_errors', $_SESSION)
      && $_SESSION['session_field_errors']) {
      self::$session_field_errors = $_SESSION['session_field_errors'];
      unset($_SESSION['session_field_errors']);
    }
    if(array_key_exists('session_field_values', $_SESSION)
      && $_SESSION['session_field_values']) {
      self::$session_field_values = $_SESSION['session_field_values'];
      unset($_SESSION['session_field_values']);
    }
    session_write_close();
  }


  /**
   * set the form invalid
   * @return void
   */
  public function setInvalid() {
    session_start();
    if(!array_key_exists('form_conf_invalid', $_SESSION)) {
      $_SESSION['form_conf_invalid'] = true;
    }
    if(array_key_exists('form_conf_invalid', $_SESSION)
      && !$_SESSION['form_conf_invalid']) {
      $_SESSION['form_conf_invalid'] = true;
    }
    session_write_close();
  }


  /**
   * set the form as successful
   * @return void
   */
  public static function setSuccess() {
    session_start();
    if(!array_key_exists('form_conf_success', $_SESSION)) {
      $_SESSION['form_conf_success'] = true;
    }
    if(array_key_exists('form_conf_success', $_SESSION)
      && !$_SESSION['form_conf_success']) {
      $_SESSION['form_conf_success'] = true;
    }
    session_write_close();
  }


  /**
   * set the last successful entry's ID in the session
   * @return void
   */
  public static function setEntryID($entry_id) {
    session_start();
    if(!array_key_exists('entry_id', $_SESSION)) {
      $_SESSION['entry_id'] = $entry_id;
    }
    session_write_close();
  }


  /**
   * clear messages in the session
   * @return void
   */
  public static function clearMessages() {
    session_start();
    foreach(self::$messages_reference as $m) {
      if(array_key_exists($m, $_SESSION)) {
        unset($_SESSION[$m]);
      }
    }
    session_write_close();
  }


  /**
   * set values after submission if the form has errors
   * @return void
   */
  public static function setValuesIfErrorsExist($source_fields) {
    session_start();
    if(!array_key_exists('session_field_values', $_SESSION)) {
      $_SESSION['session_field_values'] = array();
      foreach($source_fields as $k => $v) {
        $_SESSION['session_field_values'][$k] = $v;
      }
    }
    session_write_close();
  }


  /**
   * get a session field value
   * @param  $key string
   * @return string or boolean
   */
  private function getSessionFieldValue($key) {
    if(array_key_exists($key, self::$session_field_values)) {
      return self::$session_field_values[$key];
    }
    return false;
  }


  /**
   * set the defaults path (should be in a folder outside of vendor that won't get overriden)
   * @param $path string
   */
  public static function setDefaultsPath($path) {
    self::$defaults_path = $path;
  }


  /**
   * get the shared configuration file for Mr. Spicy forms
   * @return string
   */
  public static function getDefaultsFile() {
    $defaults_path = self::$defaults_path;
    if(strlen($defaults_path) && file_exists($defaults_path)) {
      return $defaults_path;
    }
    return __DIR__.'/../forms-defaults.php';
  }


  /**
   * get the shared Mr. Spicy Configuration
   * @return array
   */
  public static function getDefaultsArray() {
    return include self::getDefaultsFile();
  }

  /**
   * get the submit action uri
   * @return string
   */
  public static function getSubmitActionURI() {
    return self::$submit_action_uri;
  }

  /**
   * set the submit action uri
   * @return string
   */
  public static function setSubmitActionURI() {
    $default_settings = self::getDefaultsArray();
    if(
      array_key_exists('form_action', $default_settings)
      && $default_settings['form_action'] !== null
      && strlen($default_settings['form_action']))
    {
      self::$submit_action_uri = $default_settings['form_action'];
      return;
    }
    self::$submit_action_uri = strstr(__DIR__.'/FormSubmit.php', '/wp-content');
  }
}
