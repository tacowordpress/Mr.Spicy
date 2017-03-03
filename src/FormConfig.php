<?php
if(!class_exists('FormConfig')) {
  class FormConfig extends FormConfBase {

    public function getFields() {
      $fields = array();
      return array_merge(parent::getFields(), $fields);
    }

    public function getPublic() {
      return true;
    }

    public function getPubliclyQueryable() {
      return false;
    }

    public function excludeFromSearch() {
      return true;
    }

     public function save($exclude_post=false) {
       // I know. This is bad.
      $fields = unserialize(unserialize(stripslashes($this->get('fields'))));
      $this->set('fields', serialize($fields));
      return parent::save($exclude_post);
    }
  }
}
