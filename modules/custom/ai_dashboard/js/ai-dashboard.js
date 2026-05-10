(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiDashboard = {
    attach: function (context) {
      once('ai-dashboard-init', '#ai-dashboard-app', context).forEach(function () {
        // Phase 1 skeleton — list rendering + polling lands in P3.
      });
    },
  };
})(Drupal, once);
