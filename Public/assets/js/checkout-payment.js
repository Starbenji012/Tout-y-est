// Affichage uniquement : ce compteur ne prolonge ni ne modifie la réservation serveur.
(() => {
  const page = document.querySelector('[data-payment-page]');
  const clock = page?.querySelector('[data-reservation-clock]');
  if (!clock) return;
  const seconds = Number(page.dataset.reservationSeconds);
  if (!Number.isFinite(seconds) || seconds <= 0) return;
  const navigation = performance.getEntriesByType('navigation')[0];
  const elapsedBeforeScript = Math.max(0, performance.now() - (navigation?.responseStart ?? performance.now()));
  const duration = Math.max(0, seconds * 1000 - elapsedBeforeScript);
  const start = performance.now();
  const wallStart = Date.now();
  let elapsedMax = 0;
  let timer;
  const update = () => {
    // Un retour en arrière de l'horloge locale ne peut pas ajouter du temps.
    const elapsed = Math.max(elapsedMax, performance.now() - start, Date.now() - wallStart, 0);
    elapsedMax = elapsed;
    const remaining = Math.max(0, Math.ceil((duration - elapsed) / 1000));
    clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (remaining > 0) return;
    clearInterval(timer);
    page.querySelector('[data-reservation-title]').textContent = 'Réservation terminée';
    page.querySelector('[data-reservation-running]').hidden = true;
    page.querySelector('[data-reservation-help]').hidden = true;
    const methods = page.querySelector('[data-payment-methods]');
    if (methods) methods.hidden = true;
    page.querySelector('[data-reservation-notice]').textContent = 'Le délai de réservation est écoulé. Retrouvez votre panier pour vérifier la disponibilité. Le serveur confirmera l’état de votre commande lors de la prochaine consultation.';
  };
  timer = setInterval(update, 1000);
  document.addEventListener('visibilitychange', update);
  window.addEventListener('pageshow', update);
  update();
})();
