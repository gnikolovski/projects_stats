(function ($) {
  'use strict';
  $(document).ready(function () {
    if (drupalSettings.collapsibleList === 0) {
      return;
    }

    $('.projects').hide();

    $('.project-type > a').on('click', function (e) {
      e.preventDefault();

      var isChildVisible = $(this).parent().children('.projects').is(':visible');
      if (isChildVisible) {
        $(this).parent().children('.projects').slideUp();
        $(this).parent().removeClass('active');
      }
      else {
        $(this).parent().children('.projects').slideDown();
        $(this).parent().addClass('active');
      }
    });
  });
})(jQuery);
