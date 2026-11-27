(() => {
  'use strict';
  const form = document.querySelector('.admin-product-form');
  const types = form?.querySelector('[data-product-type]');
  if (!types) return;
  const simple = form.querySelector('[data-simple-creation]');
  const variable = form.querySelector('[data-variable-creation]');
  // Ancien navigateur : garder les contrôles natifs éditables plutôt que perdre
  // des valeurs si l'événement formdata n'est pas pris en charge.
  const canSerializeDisabled = 'FormDataEvent' in window;
  const syncType = () => {
    const enabled = types.querySelector('[value="variable"]').checked;
    simple.hidden = simple.disabled = enabled;
    variable.hidden = variable.disabled = !enabled;
    if (enabled) variable.querySelector('[data-creation-details]').open = true;
  };
  types.addEventListener('change', syncType);
  const rows = [...form.querySelectorAll('[data-creation-variant]')];
  const updateCount = () => {
    const count = form.querySelector('[data-creation-count]');
    if (count) count.textContent = rows.length + ' versions possibles · ' + rows.filter(row => row.querySelector('[data-creation-selected]').checked).length + ' sélectionnées';
  };
  rows.forEach(row => {
    const selected = row.querySelector('[data-creation-selected]');
    const mode = row.querySelector('[data-creation-sku-mode]');
    const manual = row.querySelector('[data-creation-sku-manual]');
    const sync = () => {
      row.querySelectorAll('[data-creation-row-field]').forEach(control => { control.disabled = canSerializeDisabled && !selected.checked; });
      manual.hidden = mode.value !== 'manual';
      manual.querySelector('input').required = selected.checked && mode.value === 'manual';
      row.querySelector('[data-creation-price]').required = selected.checked;
      row.querySelector('[data-creation-selection]').textContent = selected.checked ? 'Sélectionnée' : 'Non sélectionnée';
      updateCount();
    };
    row.addEventListener('change', sync);
    row.querySelectorAll('input,select').forEach(input => input.addEventListener('invalid', () => { if (input.closest('[data-creation-advanced]')) input.closest('[data-creation-advanced]').open = true; variable.querySelector('[data-creation-details]').open = true; }));
    window.addEventListener('pageshow', sync);
    sync();
  });
  // Les champs grisés ne participent pas à la validation native, mais le contrat HTTP
  // exige les six valeurs de TOUTES les combinaisons, même non sélectionnées.
  // formdata couvre aussi bien le POST natif que new FormData(form), sans miroirs
  // cachés ni doublons qui feraient dépasser max_input_vars à 100 variantes.
  form.addEventListener('formdata', event => {
    if (variable.disabled || !types.querySelector('[value="variable"]').checked) return;
    rows.forEach(row => row.querySelectorAll('[data-creation-row-field]').forEach(control => {
      if (control.disabled && control.name && !event.formData.has(control.name)) event.formData.append(control.name, control.value);
    }));
  });
  form.querySelectorAll('[data-creation-definition]').forEach(input => input.addEventListener('input', () => {
    variable.querySelector('[data-creation-feedback]').textContent = 'Options modifiées : affichez de nouveau les versions avant d’enregistrer.';
  }));
  window.addEventListener('pageshow', syncType);
  syncType();
})();
