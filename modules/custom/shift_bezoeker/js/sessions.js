(function ($, Drupal) {
  Drupal.behaviors.sessionOverview = {
    attach: function (context, settings) {
      $('.grid-session', context).once('session-click').on('click', function () {
        const $this = $(this);
        const title = $this.data('title');
        const desc = $this.data('description');
        const time = $this.data('time');
        const location = $this.data('location');
        const id = $this.data('id');

        $('#modal-title').text(title);
        $('#modal-description').text(desc || 'Geen beschrijving beschikbaar.');
        $('#modal-time').text(time);
        $('#modal-location').text(location);

        $('.session-modal-overlay').addClass('active');
        $('body').css('overflow', 'hidden');
      });

      $('.close-modal, .session-modal-overlay').on('click', function (e) {
        if (e.target === this || $(this).hasClass('close-modal')) {
          $('.session-modal-overlay').removeClass('active');
          $('body').css('overflow', 'auto');
        }
      });
    }
  };
})(jQuery, Drupal);
