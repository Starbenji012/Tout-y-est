// Présentation du filtre seulement : la soumission reste un GET classique.
(() => {
  document.querySelectorAll('[data-select-control]').forEach(select => window.SelectControl?.enhance(select));
  const form = document.querySelector('[data-admin-orders-filter]');
  if (!form) return;
  let submitting = false;
  form.addEventListener('change', event => {
    if (!['etat', 'encaisser'].includes(event.target.name) || submitting) return;
    submitting = true;
    form.requestSubmit();
  });
  window.addEventListener('pageshow', () => { submitting = false; });
})();
