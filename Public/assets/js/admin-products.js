// Le composant améliore la présentation ; le formulaire GET reste natif.
(() => {
  document.querySelectorAll('.admin-products [data-select-control]').forEach(select => {
    window.SelectControl?.enhance(select);
  });
  const form = document.querySelector('.admin-products__filters');
  if (!form) return;
  let submitting = false;
  form.addEventListener('submit', event => {
    if (submitting) { event.preventDefault(); return; }
    submitting = true;
  });
  form.addEventListener('change', event => {
    if (submitting || !['statut', 'categorie'].includes(event.target.name)) return;
    // Soumission native : tous les champs restent inclus, sans AJAX ni frappe automatique.
    form.requestSubmit();
  });
  window.addEventListener('pageshow', () => { submitting = false; });
})();
