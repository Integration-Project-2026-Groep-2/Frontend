(function () {
  const eventDatum = new Date('2026-06-22T10:00:00');
  function update() {
    const nu = new Date();
    const verschil = eventDatum - nu;
    if (verschil <= 0) return;

    document.getElementById('countdown-dagen').innerText =
      String(Math.floor(verschil / 864e5)).padStart(2, '0');
    document.getElementById('countdown-uren').innerText =
      String(Math.floor((verschil % 864e5) / 36e5)).padStart(2, '0');
    document.getElementById('countdown-minuten').innerText =
      String(Math.floor((verschil % 36e5) / 6e4)).padStart(2, '0');
    document.getElementById('countdown-seconden').innerText =
      String(Math.floor((verschil % 6e4) / 1000)).padStart(2, '0');
  }
  setInterval(update, 1000);
  update();
})();