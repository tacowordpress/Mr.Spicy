<?php

class FormEntry extends \Taco\Post {

  public $form_config_id = null;
  public $form_config = null;

  public function getFields() {
    $fields = array(
      'form_config' => array(
        'type' => 'select',
        'options' => self::getFormConfigs(),
      ),
      'captured_data' => [
        'type' => 'hidden',
        'readonly' => true
      ]
    );
    $form_conf_fields = self::getFieldsFromFormEntryConf();
    return array_merge(
      $fields,
      $form_conf_fields
    );
  }


  public function loadFormConfig() {
    if(!$this->form_config_id) {
      $post_id = self::getPostID();
      if(!is_numeric($post_id)) return array();
      $conf_id = get_post_meta($post_id, 'form_config', true);
      if(!is_numeric($conf_id)) return array();
    } else {
      $conf_id = $this->form_config_id;
    }
    $this->form_config = \FormConfig::find($conf_id);
  }


  public function getFieldsFromFormEntryConf() {

    $this->loadFormConfig();
    $form_config = $this->form_config;

    if(!\AppLibrary\Obj::iterable($form_config)) return array();
    if(!strlen($form_config->get('fields'))) return array();

    $fields = unserialize(unserialize($form_config->get('fields')));
    if(!\AppLibrary\Arr::iterable($fields)) return array();
    return $fields;
  }


  public function isValid($fields) {
    // do validation stuff
    if(!array_key_exists('form_config', $_POST)) return false;
    $form_config = \FormConfig::find($_POST['form_config']);
    if(!\AppLibrary\Obj::iterable($form_config)) return false;
    $is_valid = \Taco\MrSpicy::validate($fields, $form_config);

    $use_ajax = (array_key_exists('use_ajax', $_POST))
      ? true
      : false;

    if(!$is_valid && $use_ajax) {
      http_response_code(400);
      echo json_encode(
        array(
          'error' => true,
          'message' => $this->error_messages
        )
      );
      exit;
    } elseif(!$is_valid) {
      return false;
    }
    return true;
  }

  public function save() {
    \Taco\MrSpicy::setSuccess();
    $fields = $this->getFields();
    $captured_data = [];
    foreach($fields as $k => $v) {
      if($k === 'captured_data' || $k === 'form_config') continue;
      $captured_data[$k] = $this->get($k);
    }
    $form_config = \FormConfig::find($this->get('form_config'));
    $captured_data['form_configuration'] = $form_config->get('post_title');
    $this->set('captured_data', json_encode($captured_data));
    return parent::save();
  }


  public function getURLAfterSuccess() {
    if($this->form_config && $this->form_config->get('success_redirect_url')) {
      $url = $this->form_config->get('success_redirect_url');
    } else {
      $url = $_SERVER['HTTP_REFERER'];
    }
    header(sprintf('Location: %s', $url));
    exit;
  }

  public static function getPostID() {
    global $post;

    if($post) {
      return $post->ID;
    }

    if(is_admin()) {
      if($_GET['post']) {
        return $_GET['post'];
      }
      if(!array_key_exists('post', $_GET)
        && !isset($post)) {
        return false;
      }
    }
    if(array_key_exists('ID', $_POST)) {
      return $_POST['ID'];
    }
    return false;
  }

  public static function getFormConfigs() {
    return \FormConfig::getPairs();
  }

  public function getSingular() {
    return 'Form Entry';
  }

  public function getPlural() {
    return 'Form Entries';
  }

  public function excludeFromSearch() {
    return true;
  }

  public static function getAdditionalSharedColumns() {
    $form_config_defaults = \Taco\MrSpicy::getDefaultsArray();
    if(!array_key_exists('shared_configuration_extra_fields', $form_config_defaults)) {
      return [];
    }
    return $form_config_defaults['shared_configuration_extra_fields'];
  }

  public function getAdminColumns() {
    $additional_shared_columns = self::getAdditionalSharedColumns();
    return array_merge(
      $additional_shared_columns,
      ['form_config']
    );
  }

   public function getPostTypeConfig() {
    return array_merge(parent::getPostTypeConfig(), array(
      'show_in_menu'=>'edit.php?post_type=form-config',
      'publicly_queryable' => false,
      'exclude_from_search' => true
    ));
  }

}
