(function ($, Drupal, once) {
  Drupal.behaviors.sessionOverview = {
    attach: function (context, settings) {
      once('session-click', '.grid-session', context).forEach(function (element) {
        $(element).on('click', function () {
          const $this = $(this);
          const id = $this.data('id');
          const title = $this.data('title');
          const desc = $this.data('description');
          const time = $this.data('time');
          const location = $this.data('location');

          $('#modal-title').text(title);
          $('#modal-description').text(desc || 'Geen beschrijving beschikbaar.');
          $('#modal-time').show().text(time);
          $('#modal-location').show().text(location);
          
          const registered = $this.data('registered') === true || $this.data('registered') === 'true';
          if (registered) {
            $('.register-btn').hide();
            $('.cancel-btn').show().data('session-id', id);
          } else {
            $('.cancel-btn').hide();
            $('.register-btn')
              .show()
              .data('session-id', id);
          }

          $('.session-modal-overlay').addClass('active');
          $('body').css('overflow', 'hidden');
        });
      });

      // Handle registratie knop
      once('register-click', '.register-btn', context).forEach(function (element) {
        $(element).on('click', function() {
          const sessionId = $(this).data('session-id');
          if (sessionId) {
            window.location.href = `/sessie/inschrijven/${sessionId}`;
          }
        });
      });

      // Handle uitschrijven knop
      once('cancel-click', '.cancel-btn', context).forEach(function (element) {
        $(element).on('click', function() {
          const sessionId = $(this).data('session-id');
          if (sessionId && confirm('Weet je zeker dat je je wilt uitschrijven voor deze sessie?')) {
            window.location.href = `/sessie/uitschrijven/${sessionId}`;
          }
        });
      });

      once('location-click', '.clickable-location', context).forEach(function (element) {
        $(element).on('click', function () {
          const $this = $(this);
          const name = $this.data('name');
          const address = $this.data('address');
          const capacity = $this.data('capacity');
          const status = $this.data('status');

          $('#modal-title').text(name);
          $('#modal-description').html(`
            <strong>Adres:</strong> ${address || 'Niet opgegeven'}<br>
            <strong>Capaciteit:</strong> ${capacity} personen<br>
            <strong>Status:</strong> ${status}
          `);
          
          $('#modal-time').hide();
          $('#modal-location').hide();
          $('.register-btn').hide();
          $('.cancel-btn').hide();

          $('.session-modal-overlay').addClass('active');
          $('body').css('overflow', 'hidden');
        });
      });

      $('.close-modal, .session-modal-overlay').on('click', function (e) {
        if (e.target === this || $(this).hasClass('close-modal')) {
          $('.session-modal-overlay').removeClass('active');
          $('body').css('overflow', 'auto');
        }
      });
    }
  };
})(jQuery, Drupal, once);
