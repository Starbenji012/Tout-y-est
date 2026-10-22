// Le GET reste traité par le serveur ; aucun prix, compte ou résultat n'est calculé ici.
(() => {
  const form = document.querySelector('[data-orders-filter]');
  if (!form) return;
  const period = form.elements.namedItem('period');
  const month = form.elements.namedItem('month');
  const monthField = form.querySelector('[data-orders-month]');
  if (!period || !month || !monthField) return;
  const periodPicker = window.SelectControl?.enhance(period);
  const monthPicker = window.SelectControl?.enhance(month);
  let navigating = false;
  const sync = () => {
    const custom = period.value === 'month';
    monthField.hidden = !custom;
    month.disabled = !custom || ![...month.options].some(option => option.value !== '');
    periodPicker?.sync();
    monthPicker?.sync();
  };
  const apply = () => {
    if (!navigating) form.requestSubmit();
  };
  period.addEventListener('change', () => {
    if (navigating) return;
    sync();
    if (period.value === 'month') {
      // Attendre un vrai choix, même lorsqu'un seul mois est disponible.
      month.value = '';
      monthPicker?.sync();
      if (!month.disabled) { if (monthPicker) monthPicker.focus(); else month.focus(); }
    } else apply();
  });
  month.addEventListener('change', () => {
    if (period.value === 'month' && month.value !== '') apply();
  });
  form.addEventListener('submit', () => { navigating = true; });
  window.addEventListener('pageshow', () => { navigating = false; sync(); });
  sync();
})();
