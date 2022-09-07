(function ($, Drupal) {
  'use strict'; 

  Drupal.behaviors.idme = {
    'attach': function (context) {
      // Find the IDme data, and parse.
      $.each($('#idme_wrapper').data(), function(i, v){
        // Now we have the long set of check and fill.
        switch(i) {
          case 'fname':
            var shell = $('.webform-type-webform-name').attr('id').replace("--wrapper", "-first");
            $('#'+shell).val(v);
            break;
          case 'lname':
            var shell = $('.webform-type-webform-name').attr('id').replace("--wrapper", "-last");
            $('#'+shell).val(v);
            break;
          case 'email':
            $('input.form-email').val(v);
            break;
          case 'street':
            $('.address--wrapper .address-line1').val(v);
            break;
          case 'state':
            $('.address--wrapper select.administrative-area').val(v);
            break;
          case 'zip':
            $('.address--wrapper .postal-code').val(v);
            break;
          case 'city':
            $('.address--wrapper .locality').val(v);
            break;
          case 'phone':
            $('input.form-tel').val(v);
            break;
          default:
            console.log(i);
        }
      });
    }
  };
})(jQuery, Drupal);
