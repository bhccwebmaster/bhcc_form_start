/**
 * @file
 * Privacy notice
 */
(function ($, once) {

  /**
   * Privacy notice checkbox behaviour.
   * @param  {jQuery} element
   *   The link element container.
   */
  function privacy_checkbox_behaviours(element) {

    // Find the checkbox using class value.
    var privacy_checkbox = element.find('.js-privacy-checkbox');

    // Find the link button using class value.
    var form_link_button = element.find('.js-cta-button');

    // Click handler for the cta button.
    form_link_button.click(function (event) {
      if ($(this).hasClass('js-link-disabled')) {
       event.preventDefault();
      }
    });

    // Privacy checkbox change behaviour.
    privacy_checkbox.change(function () {

      // Enable the link and show the button.
      if (this.checked) {
        form_link_button.removeClass('link-disabled js-link-disabled');
      }
      // Disable the link and 'dim' the button.
      else{
        form_link_button.addClass('link-disabled js-link-disabled');
      }
    });
  }

  Drupal.behaviors.bhcc_form_start_privacy = {
    attach: function (context, settings) {
      $(once('bhcc_form_start_privacy', '.js-privacy-form-start', context)).each(function () {
        privacy_checkbox_behaviours($(this));
      });
    }
  }

})(jQuery, once);
