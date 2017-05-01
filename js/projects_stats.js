(function ($) {
  'use strict';
  $(document).ready(function () {
    if (drupalSettings.collapsibleMenu === 0) {
      return;
    }

    $('.all-projects ul').hide();

    $('.project-type > a').on('click', function (e) {
      e.preventDefault();

      var isChildVisible = $(this).parent().children('.projects').is(':visible');
      if (isChildVisible) {
        $(this).parent().children('.projects').slideUp();
      }
      else {
        $(this).parent().children('.projects').slideDown();
      }
    });
  });
})(jQuery);
