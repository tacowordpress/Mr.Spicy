<?php

/* TODO: Figure out how to allow customization of this outside of the composer vendor directory
 * This file allows defaults to be assigned globally for all Mr. Spicy forms.
 * These defaults will be overridden if the developer specifies these values
 *   in the form config (hardcoded), or if the admin adds values in the wp-admin (if applicable).
 */
return array(
  'form_action' => '/wp-content/themes/taco-theme/app/core/vendor/tacowordpress/MrSpicy/src/core/FormSubmit.php',
  'error_message' => 'There were some errors with your form submission. Please correct and try again.',
  'success_message' => 'Thanks for your message',
);
