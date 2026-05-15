(function ($, Drupal, once) {
  Drupal.behaviors.sessionOverview = {
    attach: function (context, settings) {
      // Gebruik delegatie op het document om dubbele event handlers te voorkomen
      once('shift-bezoeker-init', 'html', context).forEach(function() {
        
        // Klik op een sessie in de grid
        $(document).on('click', '.grid-session', function(e) {
          e.preventDefault();
          const $this = $(this);
          const id = $this.data('id');
          const title = $this.data('title');
          const desc = $this.data('description');
          const time = $this.data('time');
          const location = $this.data('location');

          $('#modal-title').text(title);
          $('#modal-description').text(desc || Drupal.t('Geen beschrijving beschikbaar.'));
          $('#modal-time').show().text(time);
          $('#modal-location').show().text(location);
          
          const registered = $this.data('registered') === true || $this.data('registered') === 'true';
          if (registered) {
            $('.register-btn').hide();
            $('.cancel-btn').show().data('session-id', id);
          } else {
            $('.cancel-btn').hide();
            $('.register-btn').show().data('session-id', id);
          }

          $('.session-modal-overlay').addClass('active');
          $('body').css('overflow', 'hidden');
        });

        // Handle registratie knop
        $(document).on('click', '.register-btn', function(e) {
          e.preventDefault();
          const sessionId = $(this).data('session-id');
          if (sessionId) {
            window.location.href = `/sessie/inschrijven/${sessionId}`;
          }
        });

        // Handle uitschrijven knop
        $(document).on('click', '.cancel-btn', function(e) {
          e.preventDefault();
          const sessionId = $(this).data('session-id');
          if (sessionId && confirm(Drupal.t('Weet je zeker dat je je wilt uitschrijven voor deze sessie?'))) {
            window.location.href = `/sessie/uitschrijven/${sessionId}`;
          }
        });

        // Handle locatie klik
        $(document).on('click', '.clickable-location', function() {
          const $this = $(this);
          $('#modal-title').text($this.data('name'));
          $('#modal-description').html(`
            <strong>${Drupal.t('Adres')}:</strong> ${$this.data('address') || Drupal.t('Niet opgegeven')}<br>
            <strong>${Drupal.t('Capaciteit')}:</strong> ${$this.data('capacity')} ${Drupal.t('personen')}<br>
            <strong>${Drupal.t('Status')}:</strong> ${$this.data('status')}
          `);
          $('#modal-time').hide();
          $('#modal-location').hide();
          $('.register-btn').hide();
          $('.cancel-btn').hide();
          $('.session-modal-overlay').addClass('active');
          $('body').css('overflow', 'hidden');
        });

        // Sluit modal
        $(document).on('click', '.close-modal, .session-modal-overlay', function(e) {
          if (e.target === this || $(this).hasClass('close-modal')) {
            $('.session-modal-overlay').removeClass('active');
            $('body').css('overflow', 'auto');
          }
        });
      });
    }
  };
})(jQuery, Drupal, once);
