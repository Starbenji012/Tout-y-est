// Le formulaire reste un POST classique, sans conversion monétaire dans le navigateur.
(() => {
  const form = document.querySelector('.admin-product-form');
  if (!form) return;
  const feedback = form.querySelector('[data-product-form-feedback]');
  form.querySelectorAll('[data-select-control]').forEach(select => {
    window.SelectControl?.enhance(select);
    const trigger = document.getElementById(select.id + '-trigger');
    if (select.getAttribute('aria-invalid') === 'true') trigger?.setAttribute('aria-invalid', 'true');
    // Un select amélioré est masqué : guider le focus vers son contrôle visible.
    select.addEventListener('invalid', event => {
      if (!select.hidden) return;
      event.preventDefault();
      const trigger = document.getElementById(select.id + '-trigger');
      trigger?.setAttribute('aria-invalid', 'true');
      trigger?.focus();
      if (feedback) { feedback.hidden = false; feedback.textContent = 'Choisissez une valeur pour les champs obligatoires.'; }
    });
    select.addEventListener('change', () => {
      if (select.getAttribute('aria-invalid') !== 'true') document.getElementById(select.id + '-trigger')?.removeAttribute('aria-invalid');
      if (feedback) feedback.hidden = true;
    });
  });
  let submitting = false;
  form.addEventListener('submit', event => {
    if (submitting) { event.preventDefault(); return; }
    submitting = true;
    form.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => { submitting = false; form.removeAttribute('aria-busy'); });
})();
