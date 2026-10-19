// Le clic unique valide la livraison, puis appelle la transaction S1.7 inchangée.
(() => {
  const page = document.querySelector('[data-checkout-page]');
  if (!page) return;
  const form = page.querySelector('[data-checkout-form]');
  const order = page.querySelector('[data-order-form]');
  const submit = page.querySelector('[data-order-submit]');
  const summary = page.querySelector('[data-checkout-summary]');
  const message = page.querySelector('[data-order-message]');
  const panel = page.querySelector('#checkout-addresses');
  const change = page.querySelector('[data-change-address]');
  const selected = page.querySelector('[data-selected-address]');
  const fieldsBox = page.querySelector('[data-address-fields]');
  const saveBox = page.querySelector('[data-save-options]');
  const save = form.elements.namedItem('save_address');
  const defaultBox = page.querySelector('[data-default-option]');
  const makeDefault = form.elements.namedItem('make_default');
  const zone = form.elements.namedItem('id_zone_livraison');
  const fields = [...fieldsBox.querySelectorAll('input, textarea, select')];
  const initialSummary = summary.innerHTML;
  let pending = false, reader, validated = false;
  const closeAddresses = () => { panel.hidden = true; change.setAttribute('aria-expanded', 'false'); };
  const syncSave = () => {
    defaultBox.hidden = !save.checked;
    makeDefault.disabled = !save.checked || save.disabled;
    if (makeDefault.disabled) makeDefault.checked = false;
  };
  const read = async (response) => {
    let result;
    try { result = await response.json(); } catch { throw new Error('Réponse indisponible. Rechargez la page et réessayez.'); }
    if (!response.ok) { const error = new Error(result.error || 'Impossible de poursuivre.'); error.fields = result.errors || {}; throw error; }
    return result;
  };
  const refresh = async (id) => {
    reader?.abort(); summary.innerHTML = initialSummary; summary.removeAttribute('aria-busy');
    if (!id) return;
    const controller = new AbortController(); reader = controller; summary.setAttribute('aria-busy', 'true');
    try {
      const result = await read(await fetch(`/api/checkout/recapitulatif?zone=${encodeURIComponent(id)}`, {headers: {Accept: 'application/json'}, signal: controller.signal}));
      if (!controller.signal.aborted) { summary.innerHTML = result.html; window.lucide?.createIcons(); }
    } catch (error) { if (error.name !== 'AbortError') message.textContent = error.message; }
    finally { if (reader === controller) summary.removeAttribute('aria-busy'); }
  };
  const choose = () => {
    const radio = form.querySelector('[name="address_id"]:checked');
    const address = radio?.dataset.address ? JSON.parse(radio.dataset.address) : null;
    fieldsBox.hidden = Boolean(address);
    fields.forEach((field) => { field.disabled = Boolean(address) || (field === zone && zone.options.length === 1); });
    saveBox.hidden = Boolean(address); save.disabled = Boolean(address); syncSave();
    selected.hidden = !address; selected.replaceChildren();
    if (address) {
      const title = document.createElement('strong'); title.textContent = `${address.prenom_destinataire} ${address.nom_destinataire}`;
      const detail = document.createElement('p'); detail.textContent = `${address.adresse} — ${address.commune} · ${address.telephone}`;
      selected.append(title, detail);
    }
    closeAddresses(); validated = false; syncZone(); refresh(address ? address.id_zone_livraison : zone.value);
  };
  change.addEventListener('click', () => { panel.hidden = !panel.hidden; change.setAttribute('aria-expanded', String(!panel.hidden)); if (!panel.hidden) panel.querySelector('input:checked, input')?.focus(); });
  panel.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeAddresses(); change.focus(); } });
  page.querySelector('[data-new-address]').addEventListener('click', () => { form.querySelector('[name="address_id"][value="0"]').checked = true; choose(); fields[0].focus(); });
  form.addEventListener('input', () => { validated = false; });
  form.addEventListener('change', (event) => {
    validated = false; message.textContent = '';
    if (event.target.name === 'address_id') choose();
    else if (event.target === zone) { syncZone(); refresh(zone.value); }
    else if (event.target === save) syncSave();
  });

  // Le champ réel reste la source de valeur, le menu enrichi gère le clavier.
  const picker = document.createElement('div'); picker.className = 'checkout-zone-picker';
  const trigger = document.createElement('button'); trigger.type = 'button'; trigger.id = 'checkout-zone-trigger'; trigger.className = 'checkout-zone-trigger';
  trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-controls', 'checkout-zone-options');
  trigger.setAttribute('aria-required', 'true');
  trigger.setAttribute('aria-describedby', 'error-id_zone_livraison checkout-zones-notice');
  const label = form.querySelector('label[for="checkout-zone"]'); label.id = 'checkout-zone-label'; label.htmlFor = trigger.id;
  const value = document.createElement('span'); value.id = 'checkout-zone-value'; trigger.append(value); trigger.setAttribute('aria-labelledby', `${label.id} ${value.id}`);
  const chevron = document.createElement('i'); chevron.dataset.lucide = 'chevron-down'; chevron.setAttribute('aria-hidden', 'true'); trigger.append(chevron);
  const list = document.createElement('div'); list.id = 'checkout-zone-options'; list.hidden = true; list.setAttribute('role', 'listbox'); list.setAttribute('aria-labelledby', label.id);
  const options = [...zone.options].filter((option) => option.value).map((option) => {
    const button = document.createElement('button'); button.type = 'button'; button.tabIndex = -1; button.setAttribute('role', 'option'); button.dataset.value = option.value;
    const name = document.createElement('span'); name.textContent = option.dataset.name;
    const fee = document.createElement('span'); fee.textContent = option.dataset.fee; fee.className = 'checkout-zone-fee'; button.append(name, fee);
    button.addEventListener('click', () => { zone.value = option.value; closeZone(); trigger.focus(); zone.dispatchEvent(new Event('change', {bubbles: true})); });
    list.append(button); return button;
  });
  function syncZone() { value.textContent = zone.selectedOptions[0]?.textContent || 'Choisissez une zone'; trigger.disabled = zone.disabled; options.forEach((option) => option.setAttribute('aria-selected', String(option.dataset.value === zone.value))); }
  function closeZone() { list.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }
  function openZone() { list.hidden = false; trigger.setAttribute('aria-expanded', 'true'); (options.find((option) => option.dataset.value === zone.value) || options[0])?.focus(); }
  trigger.addEventListener('click', () => { if (list.hidden) openZone(); else closeZone(); });
  picker.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeZone(); trigger.focus(); }
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault(); if (list.hidden) { openZone(); return; }
      const index = options.indexOf(document.activeElement);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
      options[next]?.focus();
    }
  });
  // Un seul clic extérieur ferme les panneaux sans changer la sélection.
  document.addEventListener('click', (event) => {
    if (!picker.contains(event.target)) closeZone();
    if (!panel.contains(event.target) && !change.contains(event.target)) closeAddresses();
  }, true);
  picker.addEventListener('focusout', (event) => { if (!picker.contains(event.relatedTarget)) closeZone(); });
  zone.hidden = true; zone.required = false; picker.append(trigger, list); zone.parentElement.append(picker);

  const proceed = async (event) => {
    event.preventDefault(); if (pending || submit.disabled) return;
    page.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    fields.forEach((field) => field.removeAttribute('aria-invalid')); trigger.removeAttribute('aria-invalid');
    if (!form.reportValidity()) return;
    pending = true;
    const data = new FormData(form);
    const orderData = new FormData(order);
    const controls = [...form.elements, trigger, submit].filter((control) => 'disabled' in control);
    const previous = new Map(controls.map((control) => [control, control.disabled]));
    controls.forEach((control) => { control.disabled = true; });
    reader?.abort(); summary.removeAttribute('aria-busy'); page.setAttribute('aria-busy', 'true');
    let focus = message, leaving = false;
    try {
      message.textContent = 'Vérification de votre livraison…';
      if (!validated) {
        const result = await read(await fetch(form.action, {method: 'POST', body: data, headers: {Accept: 'application/json'}}));
        validated = true; summary.innerHTML = result.summary.html; window.lucide?.createIcons();
        // Un retry réutilise cette adresse au lieu de l'enregistrer une seconde fois.
        if (result.saved) {
          const radio = document.createElement('input'); radio.type = 'radio'; radio.name = 'address_id'; radio.value = result.addressId; radio.dataset.address = JSON.stringify(result.address);
          const wrapper = document.createElement('label'); wrapper.className = 'checkout-saved-address';
          const text = document.createElement('span'); text.textContent = `${result.address.commune} — ${result.address.adresse}`;
          wrapper.append(radio, text); panel.append(wrapper); radio.checked = true; change.hidden = false;
        }
      }
      message.textContent = 'Création de votre commande…';
      const result = await read(await fetch(order.action, {method: 'POST', body: orderData, headers: {Accept: 'application/json'}}));
      leaving = true; window.location.assign(result.url);
    } catch (error) {
      message.textContent = error.message || 'Connexion indisponible. Réessayez avec la même demande.';
      Object.entries(error.fields || {}).forEach(([name, text]) => {
        const field = form.elements.namedItem(name); field?.setAttribute('aria-invalid', 'true');
        const node = [...page.querySelectorAll('[data-error-for]')].find((item) => item.dataset.errorFor === name); if (node) node.textContent = text;
      });
      const invalid = fields.find((field) => field.getAttribute('aria-invalid') === 'true');
      focus = fieldsBox.hidden && invalid ? change : invalid === zone ? trigger : invalid || message;
      if (invalid === zone) trigger.setAttribute('aria-invalid', 'true');
    } finally {
      controls.forEach((control) => { control.disabled = previous.get(control); });
      if (validated && !fieldsBox.hidden && form.querySelector('[name="address_id"]:checked')?.dataset.address) { choose(); validated = true; }
      page.removeAttribute('aria-busy'); pending = false; submit.disabled = leaving || zone.options.length === 1;
      if (!leaving) focus.focus();
    }
  };
  form.addEventListener('submit', proceed); order.addEventListener('submit', proceed);
  choose(); window.lucide?.createIcons();
})();
