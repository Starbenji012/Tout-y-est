/* Amélioration progressive : les validations et toutes les écritures restent au serveur. */
((global) => {
  'use strict';
  const clean = value => String(value).replace(/[\p{Z}\s]+/gu, ' ').trim();
  const comparison = value => clean(value).normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('fr');
  function buildCandidates(definitions) {
    if (!Array.isArray(definitions) || definitions.length < 1 || definitions.length > 3) return {error: 'Ajoutez de une à trois options.', candidates: []};
    let count = 1;
    const names = new Set();
    for (const option of definitions) {
      const name = clean(option.nom);
      if (!name || name.length > 100 || names.has(comparison(name))) return {error: 'Chaque option doit avoir un nom différent, de 1 à 100 caractères.', candidates: []};
      names.add(comparison(name));
      if (!Array.isArray(option.valeurs) || !option.valeurs.length || option.valeurs.length > 100) return {error: 'Ajoutez les valeurs de chaque option.', candidates: []};
      const values = option.valeurs.map(clean);
      if (values.some(value => !value || value.length > 100) || new Set(values.map(comparison)).size !== values.length) return {error: 'Les valeurs doivent être renseignées et différentes.', candidates: []};
      count *= values.length;
      if (count > 100) return {error: 'Ces options créent plus de 100 combinaisons. Réduisez le nombre de valeurs.', candidates: [], count};
    }
    let candidates = [{token: '', label: ''}];
    definitions.forEach((option, i) => {
      const next = [];
      candidates.forEach(candidate => option.valeurs.forEach((label, j) => next.push({
        token: (candidate.token ? candidate.token + '|' : '') + (i + 1) + ':' + (j + 1),
        label: (candidate.label ? candidate.label + ' / ' : '') + clean(label)
      })));
      candidates = next;
    });
    return {count, candidates};
  }
  function mount(root) {
    const form = root.querySelector('[data-version-builder]');
    if (!form || form.dataset.enhanced) return;
    form.dataset.enhanced = 'true';
    // Avec JS, Entrée dans un champ doit prévisualiser, jamais retirer une valeur.
    form.querySelectorAll('[data-add-option],[data-remove-option],[data-add-value],[data-remove-value]').forEach(button => { button.type = 'button'; });
    const panel = form.querySelector('[data-options-panel]');
    const rows = form.querySelector('[data-option-rows]');
    const feedback = form.querySelector('[data-options-feedback]');
    const candidatesPanel = form.querySelector('[data-candidate-panel]');
    const save = form.querySelector('[data-save-options]');
    const addOption = form.querySelector('[data-add-option]');
    const doc = root.ownerDocument;
    let pending = false;
    let submittedButton = null;
    let submittedLabel = '';
    const make = (tag, text) => { const element = doc.createElement(tag); if (text !== undefined) element.textContent = text; return element; };
    const button = (text, attribute) => { const element = make('button', text); element.type = 'button'; element.className = 'btn ' + (attribute.startsWith('data-remove-') ? 'btn-danger' : 'btn-secondary'); element.setAttribute(attribute, ''); return element; };
    const invalidate = () => { candidatesPanel.replaceChildren(); save.hidden = true; feedback.textContent = 'Affichez les combinaisons après avoir renseigné les options.'; };
    const syncMode = () => { panel.hidden = form.querySelector('[name="mode"]:checked')?.value !== 'yes'; };
    const reindex = () => {
      [...rows.querySelectorAll('[data-option-row]')].forEach((option, index) => {
        option.querySelector('legend').textContent = 'Option ' + (index + 1);
        option.querySelector('[data-option-name]').name = `options[${index}][nom]`;
        [...option.querySelectorAll('[data-option-value]')].forEach((value, j) => { value.name = `options[${index}][valeurs][${j}]`; });
      });
      addOption.disabled = rows.querySelectorAll('[data-option-row]').length >= 3;
    };
    const addValue = option => {
      if (option.querySelectorAll('[data-value-row]').length >= 100) { feedback.textContent = 'Cent valeurs maximum.'; return; }
      const row = make('div'); row.className = 'admin-product-options__value'; row.setAttribute('data-value-row', '');
      const label = make('label', 'Valeur'); const input = make('input'); input.maxLength = 100; input.placeholder = 'Ex. Noir, Blanc, Coton'; input.setAttribute('data-option-value', '');
      label.append(input); row.append(label, button('Retirer cette valeur', 'data-remove-value')); option.querySelector('[data-value-rows]').append(row);
      reindex(); invalidate(); input.focus();
    };
    form.addEventListener('click', event => {
      const target = event.target.closest('button'); if (!target) return;
      const option = target.closest('[data-option-row]');
      if (target.hasAttribute('data-add-option')) {
        event.preventDefault(); if (rows.querySelectorAll('[data-option-row]').length >= 3) return;
        const field = make('fieldset'); field.className = 'admin-product-options__option'; field.setAttribute('data-option-row', '');
        const name = make('input'); name.maxLength = 100; name.placeholder = 'Ex. Couleur, Taille, Matière'; name.setAttribute('data-option-name', '');
        const label = make('label', 'Nom de l’option'); label.append(name);
        const values = make('div'); values.setAttribute('data-value-rows', '');
        const actions = make('div'); actions.className = 'admin-product-options__actions'; actions.append(button('+ Ajouter une valeur', 'data-add-value'), button('Retirer cette option', 'data-remove-option'));
        field.append(make('legend'), label, values, actions); rows.append(field); addValue(field); name.focus();
      } else if (target.hasAttribute('data-remove-option') && option) { event.preventDefault(); option.remove(); reindex(); invalidate(); addOption.focus(); }
      else if (target.hasAttribute('data-add-value') && option) { event.preventDefault(); addValue(option); }
      else if (target.hasAttribute('data-remove-value')) { event.preventDefault(); target.closest('[data-value-row]')?.remove(); reindex(); invalidate(); option?.querySelector('[data-add-value]')?.focus(); }
    });
    form.addEventListener('input', event => { if (event.target.matches('[data-option-name],[data-option-value]')) invalidate(); });
    form.addEventListener('change', event => {
      if (event.target.name === 'mode') syncMode();
      if (event.target.name === 'reuse') event.target.closest('.admin-product-options__candidate').querySelector('[type="checkbox"]').checked = true;
      if (event.target.name === 'selected[]' && !event.target.checked) event.target.closest('.admin-product-options__candidate').querySelector('[type="radio"]').checked = false;
      if (event.target.name === 'selected[]' || event.target.name === 'reuse') feedback.textContent = candidatesPanel.querySelectorAll('[type="checkbox"]').length + ' possibles · 0 enregistrée · ' + candidatesPanel.querySelectorAll('[type="checkbox"]:checked').length + ' sélectionnées';
    });
    form.addEventListener('submit', event => {
      if (event.submitter?.value === 'preview') {
        event.preventDefault();
        const definitions = [...rows.querySelectorAll('[data-option-row]')].map(option => ({nom: option.querySelector('[data-option-name]').value, valeurs: [...option.querySelectorAll('[data-option-value]')].map(value => value.value)}));
        const result = buildCandidates(definitions); candidatesPanel.replaceChildren(); save.hidden = true;
        if (result.error) { feedback.textContent = result.error; return; }
        const field = make('fieldset'); field.className = 'admin-product-options__candidates'; field.append(make('legend', 'Combinaisons vendues'), make('p', 'Décochez les versions non vendues et choisissez celle qui reprend la version existante.'));
        result.candidates.forEach(candidate => {
          const row = make('div'); row.className = 'admin-product-options__candidate';
          const selected = make('input'); selected.type = 'checkbox'; selected.name = 'selected[]'; selected.value = candidate.token; selected.checked = true;
          const checkLabel = make('label'); checkLabel.append(selected, doc.createTextNode(' ' + candidate.label));
          const reuse = make('input'); reuse.type = 'radio'; reuse.name = 'reuse'; reuse.value = candidate.token;
          const reuseLabel = make('label'); reuseLabel.append(reuse, doc.createTextNode(' Reprendre la version existante'));
          row.append(checkLabel, reuseLabel); field.append(row);
        });
        candidatesPanel.append(field); feedback.textContent = result.count + ' possibles · 0 enregistrée · ' + result.count + ' sélectionnées. Choisissez la version à conserver.'; save.hidden = false; return;
      }
      if (pending) { event.preventDefault(); return; }
      if (event.submitter?.value === 'configure' && (!form.querySelector('[name="reuse"]:checked') || !form.querySelector('[name="selected[]"]:checked'))) { event.preventDefault(); feedback.textContent = 'Choisissez les combinaisons et la version existante à conserver.'; return; }
      pending = true; form.setAttribute('aria-busy', 'true');
      if (event.submitter) { submittedButton = event.submitter; submittedLabel = submittedButton.textContent; submittedButton.textContent = 'Enregistrement en cours…'; }
      const status = root.querySelector('[data-version-pending]'); if (status) status.hidden = false;
    });
    global.addEventListener?.('pageshow', () => { pending = false; form.removeAttribute('aria-busy'); if (submittedButton) submittedButton.textContent = submittedLabel; const status = root.querySelector('[data-version-pending]'); if (status) status.hidden = true; });
    syncMode(); reindex();
  }
  function enhancePresentation(root) {
    root.querySelectorAll('form').forEach(form => {
      if (form.hasAttribute('data-version-builder')) return;
      let pending = false;
      let submittedButton = null;
      let submittedLabel = '';
      const update = () => {
        const count = form.querySelector('[data-selected-count]');
        if (count) { const n = form.querySelectorAll('[name="selected[]"]:checked').length; count.textContent = n + ' sélectionnée' + (n === 1 ? '' : 's'); }
      };
      form.addEventListener('change', update); update();
      form.addEventListener('submit', event => {
        if (pending) { event.preventDefault(); return; }
        pending = true; form.setAttribute('aria-busy', 'true');
        if (event.submitter) { submittedButton = event.submitter; submittedLabel = submittedButton.textContent; submittedButton.textContent = 'Enregistrement en cours…'; }
        const status = root.querySelector('[data-version-pending]'); if (status) status.hidden = false;
        // Garder le bouton submit actif : sa valeur action doit être envoyée au serveur.
      });
      global.addEventListener?.('pageshow', () => { pending = false; form.removeAttribute('aria-busy'); if (submittedButton) submittedButton.textContent = submittedLabel; const status = root.querySelector('[data-version-pending]'); if (status) status.hidden = true; });
    });
  }
  const api = {buildCandidates, mount, enhancePresentation};
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.ProductOptionsEditor = api;
  if (typeof document !== 'undefined') document.querySelectorAll('[data-version-editor]').forEach(root => { mount(root); enhancePresentation(root); });
})(typeof window !== 'undefined' ? window : globalThis);
