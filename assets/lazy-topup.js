/* Performance direction: preserve payment behavior while loading its iframe only when the user opens a top-up modal. */
(function () {
  function loadTopupFrame(modal) {
    var frame = modal && modal.querySelector('iframe[data-src]');
    if (!frame || frame.dataset.loaded === 'true') return;

    frame.src = frame.dataset.src;
    frame.dataset.loaded = 'true';
  }

  document.addEventListener('show.bs.modal', function (event) {
    loadTopupFrame(event.target);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-target="#topUpModal"]').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        var modal = document.getElementById('topUpModal');
        if (modal) loadTopupFrame(modal);
      });
    });
  });
})();
