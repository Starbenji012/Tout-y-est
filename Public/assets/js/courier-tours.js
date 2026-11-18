// Le serveur garde l'autorité ; un submit natif, sans double POST ni succès anticipé.
(() => {
  const forms = [...document.querySelectorAll('[data-tour-form]')];
  const buttons = forms.flatMap(form => [...form.querySelectorAll('button[type="submit"],button:not([type])')]);
  const initial = buttons.map(button => button.disabled);
  let busy = false;
  window.addEventListener('pageshow', () => {
    busy = false;
    buttons.forEach((button, i) => { button.disabled = initial[i]; });
    forms.forEach(form => form.removeAttribute('aria-busy'));
  });
  forms.forEach(form => {
    form.addEventListener('submit', event => {
      if (busy) { event.preventDefault(); return; }
      if (form.dataset.tourConfirm && !window.confirm(form.dataset.tourConfirm)) { event.preventDefault(); return; }
      busy = true;
      form.setAttribute('aria-busy', 'true');
      buttons.forEach(button => { button.disabled = true; });
    });
  });
})();
