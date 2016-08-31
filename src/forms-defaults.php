<?php

/* This file allows defaults to be assigned globally for all Mr. Spicy forms.
 * These defaults will be overridden if the developer specifies these values
 *   in the form config (hardcoded), or if the admin adds values in the wp-admin (if applicable).
 */
return array(
  'form_action' => null, // If you're having any issues with the automatic submit url, specify it here.
  'error_message' => 'There were some errors with your form submission. Please correct and try again.',
  'success_message' => 'Thanks for your message',
  'shared_configuration_extra_fields' => [] // use this if you need common (shared across all form configurations) admin columns like "email" or "name" to be added
);
