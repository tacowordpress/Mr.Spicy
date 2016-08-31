<?php

/* TODO: Figure out how to allow customization of this outside of the composer vendor directory
 * This file allows defaults to be assigned globally for all Mr. Spicy forms.
 * These defaults will be overridden if the developer specifies these values
 *   in the form config (hardcoded), or if the admin adds values in the wp-admin (if applicable).
 */
return array(
  'form_action' => '/wp-content/themes/taco-theme/app/core/vendor/tacowordpress/mr-spicy/src/core/FormSubmit.php',
  'error_message' => 'There were some errors with your form submission. Please correct and try again.',
  'success_message' => 'Thanks for your message',
  'shared_configuration_extra_fields' => [] // use this if you need common (shared across all form configurations) admin columns like "email" or "name" to be added
);
