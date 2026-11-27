// Une seule requête image à la fois ; aucune donnée de fichier dans le POST final.
(() => {
  const form = document.querySelector('.admin-product-form');
  const panel = form?.querySelector('[data-photo-preparation]');
  if (!panel) return;
  const input = panel.querySelector('input[type=file]');
  const list = panel.querySelector('[data-prepared-list]');
  const feedback = panel.querySelector('[data-photo-feedback]');
  const main = panel.querySelector('[name=primary_photo]');
  const batch = form.elements.photo_batch.value;
  const csrf = form.elements._token.value;
  const endpoint = '/admin/produits/photos/preparer';
  const entries = [];
  let running = false;
  let mutations = 0;
  let pendingWarning = false;
  input.multiple = true;
  panel.querySelector('[data-prepare-photo]').hidden = true;
  const random = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
  const say = message => { feedback.textContent = message; feedback.hidden = !message; };
  const active = () => entries.filter(entry => !entry.removed);
  const button = (label, action, danger = false) => {
    const node = document.createElement('button'); node.type = 'button'; node.textContent = label;
    node.className = 'btn ' + (danger ? 'btn-danger' : 'btn-secondary');
    node.addEventListener('click', action); return node;
  };
  const post = (data, progress) => new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest(); xhr.open('POST', endpoint); xhr.timeout = 180000;
    if (progress) xhr.upload.addEventListener('progress', event => { if (event.lengthComputable) progress(Math.round(event.loaded / event.total * 100)); });
    xhr.onload = () => {
      let body; try { body = JSON.parse(xhr.responseText); } catch { reject(new Error('Réponse interrompue. Réessayez.')); return; }
      if (xhr.status >= 200 && xhr.status < 300) resolve(body);
      else reject(new Error(body.error || 'Envoi refusé. Réessayez.'));
    };
    xhr.onerror = xhr.ontimeout = () => reject(new Error('Connexion interrompue ou trop lente. Réessayez : la photo ne sera pas ajoutée deux fois.'));
    xhr.send(data);
  });
  const dataFor = entry => { const data = new FormData(); data.set('_token', csrf); data.set('batch', batch); data.set('ticket', entry.ticket); return data; };
  const render = () => {
    const visible = active();
    list.hidden = visible.length === 0;
    panel.setAttribute('aria-busy',String(running || mutations > 0));
    if(pendingWarning && !running && mutations === 0 && visible.every(entry=>entry.state==='ready')){
      pendingWarning=false;say('Les photos sont prêtes. Vous pouvez enregistrer le produit.');
    }
    const focus = panel.contains(document.activeElement) ? document.activeElement : null;
    if (!visible.some(entry => entry.ticket === main.value)) main.value = visible[0]?.ticket || '';
    for (const [position, entry] of visible.entries()) {
      if(list.children[position] !== entry.node) list.insertBefore(entry.node, list.children[position] || null);
      entry.hidden.value = entry.ticket;
      entry.hidden.disabled = entry.state !== 'ready';
      const stateText = (entry.error || ({queued: 'En attente', uploading: entry.percent === 100 ? 'Traitement sécurisé de la photo…' : 'Envoi : ' + entry.percent + ' %', ready: 'Photo prête'}[entry.state])) + (main.value === entry.ticket ? ' — Principale' : '');
      if(entry.status.textContent !== stateText) entry.status.textContent = stateText;
      entry.retry.hidden = entry.state !== 'error';
      entry.up.disabled = visible.indexOf(entry) === 0;
      entry.down.disabled = visible.indexOf(entry) === visible.length - 1;
      entry.progress.hidden = entry.state !== 'uploading'; entry.progress.value = entry.percent;
    }
    if(focus?.isConnected && !focus.disabled && document.activeElement !== focus) focus.focus({preventScroll:true});
  };
  const move = (entry, direction) => {
    const visible = active(), other = visible[visible.indexOf(entry) + direction];
    if (!other) return;
    const a = entries.indexOf(entry), b = entries.indexOf(other);
    [entries[a], entries[b]] = [entries[b], entries[a]]; render();
  };
  const removeRemote = async entry => {
    mutations++; render();
    try {const data = dataFor(entry); data.set('action', 'remove'); await post(data);}
    finally {mutations--;render();}
  };
  const make = ({ticket, file, src, state = 'ready'}) => {
    const entry = {ticket, file, state, percent: 0, error: '', removed: false};
    entry.node = document.createElement('li');
    const img = document.createElement('img'); img.width = img.height = 150; img.alt = file?.name || 'Photo préparée'; img.src = src;
    entry.node.append(img);
    entry.hidden = document.createElement('input'); entry.hidden.type = 'hidden'; entry.hidden.name = 'photo_ids[]'; entry.node.append(entry.hidden);
    entry.status = document.createElement('p'); entry.status.setAttribute('role', 'status'); entry.node.append(entry.status);
    entry.progress = document.createElement('progress'); entry.progress.max = 100; entry.progress.setAttribute('aria-label', 'Progression du téléversement'); entry.node.append(entry.progress);
    const actions = document.createElement('div'); actions.className = 'admin-product-photos__actions';
    entry.up = button('Monter', () => move(entry, -1)); entry.down = button('Descendre', () => move(entry, 1));
    entry.retry = button('Réessayer', () => { entry.state = 'queued'; entry.error = ''; render(); pump(); });
    actions.append(entry.up, entry.down, button('Définir comme principale', () => { main.value = entry.ticket; render(); }), entry.retry,
      button('Retirer', async () => {
        // Pas d'abandon silencieux d'un envoi en vol : la réponse tardive sera retirée.
        entry.removed = true; entry.node.remove(); render(); input.focus();
        if (entry.objectUrl) URL.revokeObjectURL(entry.objectUrl);
        if (entry.state === 'ready') {
          try { await removeRemote(entry); } catch (error) { say(error.message + ' La préparation abandonnée expirera automatiquement.'); }
        }
      }, true));
    entry.node.append(actions); entries.push(entry); return entry;
  };
  const pump = async () => {
    if (running) return;
    running = true;
    try {
      let entry;
      while ((entry = active().find(item => item.state === 'queued'))) {
        entry.state = 'uploading'; entry.percent = 0; render();
        try {
          const data = dataFor(entry); data.set('photo', entry.file);
          const result = await post(data, percent => { entry.percent = percent; render(); });
          entry.ticket = result.ticket;
          if (entry.removed) { await removeRemote(entry); continue; }
          const duplicate = active().find(item => item !== entry && item.ticket === entry.ticket);
          if (duplicate) { entry.removed = true; entry.node.remove(); if(entry.objectUrl)URL.revokeObjectURL(entry.objectUrl); say('Cette photo était déjà ajoutée.'); continue; }
          entry.state = 'ready'; entry.percent = 100; entry.error = '';
          entry.node.querySelector('img').src = result.preview;
          entry.file = null;
          if(entry.objectUrl){URL.revokeObjectURL(entry.objectUrl);entry.objectUrl=null;}
        } catch (error) { entry.state = 'error'; entry.error = error.message; }
        render();
      }
    } finally { running = false; render(); }
  };
  const previous = [...list.querySelectorAll('[data-prepared-ticket]')].map(node => ({ticket: node.dataset.preparedTicket, src: node.querySelector('img').getAttribute('src')}));
  list.replaceChildren(); previous.forEach(make); render();
  input.addEventListener('change', () => {
    for (const file of input.files) {
      if (active().length >= 6) { say('Six photos maximum. Retirez une photo pour en ajouter une autre.'); break; }
      if (file.size < 1 || file.size > 10 * 1024 * 1024) { say(file.name + ' : 10 Mio maximum par photo, fichier non vide.'); continue; }
      const objectUrl = URL.createObjectURL(file);
      const entry = make({ticket: random(), file, src: objectUrl, state: 'queued'}); entry.objectUrl = objectUrl;
    }
    input.value = ''; render(); pump();
  });
  form.addEventListener('submit', event => {
    if (running || mutations > 0 || active().some(entry => entry.state !== 'ready')) {
      event.preventDefault(); event.stopImmediatePropagation(); say('Attendez la fin des envois, réessayez les photos en erreur ou retirez-les.'); pendingWarning=true; feedback.tabIndex = -1; feedback.focus(); return;
    }
    if (event.submitter?.name === 'creation_action') return;
    if (form.elements.statut.value === 'actif' && active().length === 0) {
      event.preventDefault(); event.stopImmediatePropagation(); say('Ajoutez au moins une photo valide pour publier, ou choisissez Brouillon.'); input.focus(); return;
    }
    input.disabled = true;
  }, true);
  window.addEventListener('pageshow', () => { input.disabled = false; });
})();
