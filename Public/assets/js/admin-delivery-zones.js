// Le serveur valide les droits, le tarif USD et le statut avant confirmation.
(() => {
  const page = document.querySelector('[data-delivery-admin]');
  if (!page) return;
  let pending = false;
  const createPanel = page.querySelector('[data-zone-create]');
  const createToggle = page.querySelector('[data-zone-create-toggle]');
  const showCreatePanel = (visible) => {
    createPanel.hidden = !visible;
    createToggle?.setAttribute('aria-expanded', String(visible));
    if (visible) {
      page.querySelectorAll('[data-zone-accordion]').forEach((panel) => { panel.open = false; });
      createPanel.querySelector('[name="nom"]')?.focus();
    }
  };
  createToggle?.addEventListener('click', () => {
    if (pending) return;
    showCreatePanel(createPanel.hidden);
  });
  page.querySelector('[data-zone-create-close]')?.addEventListener('click', () => {
    if (pending) return;
    showCreatePanel(false);
    createToggle?.focus();
  });
  // Les details natifs restent utilisables au clavier, même sans JavaScript.
  page.addEventListener('toggle', (event) => {
    const panel = event.target;
    if (panel.open && panel.matches('[data-zone-accordion]')) {
      showCreatePanel(false);
      page.querySelectorAll('[data-zone-accordion]').forEach((other) => { if (other !== panel) other.open = false; });
    }
  }, true);
  page.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-zone-form]');
    if (!form) return;
    event.preventDefault();
    if (pending) return;
    pending = true;
    const message = form.querySelector('[data-zone-message]');
    const data = new FormData(form);
    const controls = [...page.querySelectorAll('input, select, button, fieldset')];
    const previousState = new Map(controls.map((control) => [control, control.disabled]));
    controls.forEach((control) => { control.disabled = true; });
    page.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach((node) => node.removeAttribute('aria-invalid'));
    form.setAttribute('aria-busy', 'true');
    message.textContent = 'Enregistrement…';
    let updated = false;
    try {
      const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
      let payload;
      try { payload = await response.json(); }
      catch { throw new Error('Réponse indisponible. Rechargez la page puis réessayez.'); }
      if (!response.ok || !payload.success) {
        Object.entries(payload.errors || {}).forEach(([name, text]) => {
          form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true');
          const node = [...form.querySelectorAll('[data-error-for]')].find((error) => error.dataset.errorFor === name);
          if (node) node.textContent = text;
        });
        throw new Error(payload.error || 'Impossible d’enregistrer cette zone.');
      }
      page.querySelector('[data-zone-list]').innerHTML = payload.html;
      updated = true;
      if (data.get('id_zone_livraison') === '0') form.reset();
      // Le formulaire modifié est remplacé : annoncer le résultat dans sa nouvelle version.
      const target = page.querySelector(`[name="id_zone_livraison"][value="${payload.id}"]`)?.closest('form');
      if (target) target.closest('[data-zone-accordion]').open = true;
      showCreatePanel(false);
      const notice = target?.querySelector('[data-zone-message]') || message;
      notice.textContent = payload.message;
      notice.focus();
    } catch (error) {
      message.textContent = error.message || 'Connexion indisponible. Réessayez.';
      message.focus();
    } finally {
      controls.forEach((control) => { control.disabled = previousState.get(control); });
      form.removeAttribute('aria-busy');
      pending = false;
      if (updated) window.lucide?.createIcons();
    }
  });
})();
