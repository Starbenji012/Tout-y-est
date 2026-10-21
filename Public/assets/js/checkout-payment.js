// La confirmation attend le serveur ; le compteur ne décide jamais de l'état métier.
(() => {
  const form = document.querySelector('[data-cash-form]');
  if (!form || !form.addEventListener) return;
  const radio = form.querySelector('input[value="cash"]');
  const button = form.querySelector('[data-cash-submit]');
  const error = form.querySelector('[data-cash-error]');
  let pending = false;
  form.addEventListener('change', () => { button.disabled = pending || !radio?.checked; });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pending || !radio?.checked) return;
    pending = true; button.disabled = true; button.setAttribute('aria-busy', 'true'); error.textContent = '';
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'Accept': 'application/json'}});
      let data;
      try { data = await response.json(); } catch { throw new Error('Réponse serveur illisible. Réessayez sans recréer de commande.'); }
      if (!response.ok || data.success !== true) throw new Error(data.error || 'La confirmation a échoué. Réessayez.');
      try { await window.CartStore?.refresh(); } catch { /* La page suivante relira le compteur serveur. */ }
      window.location.assign(data.url);
    } catch (failure) {
      error.textContent = failure.message || 'Connexion interrompue. Réessayez la confirmation.';
      pending = false; button.disabled = !radio?.checked; button.removeAttribute('aria-busy');
    }
  });
})();

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
