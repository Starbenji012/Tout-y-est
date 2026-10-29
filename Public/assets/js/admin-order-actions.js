// Un seul POST à la fois ; l'état affiché est toujours relu après succès serveur.
(() => {
  const forms = [...document.querySelectorAll('[data-order-action]')];
  const message = document.querySelector('[data-order-action-message]');
  if (!message) return;
  const labels = {prepare:'La préparation de la commande a commencé.', deliver:'La commande a été marquée comme livrée.', collectCash:'Paiement encaissé avec succès.', cancel:'La commande a été annulée.'};
  const storageKey = 'tout-y-est:admin-order-action';
  try {
    const flash = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    const reference = new URL(location.href).searchParams.get('reference');
    if (flash?.reference === reference && Date.now()-flash.time < 60000) message.textContent = flash.text;
    sessionStorage.removeItem(storageKey);
  } catch (_) { /* Un stockage bloqué ne doit pas empêcher l'action. */ }
  let busy = false;
  forms.forEach(form => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; }));
  forms.forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const action = form.dataset.orderAction;
    const data = new FormData(form);
    const courier = String(data.get('livreur') || '').trim();
    const confirmations = {prepare:'Commencer la préparation de cette commande ?',ship:`Confier la commande à ${courier} et la mettre en livraison ?`,deliver:'Confirmer que cette commande a réellement été livrée ?',collectCash:'Confirmer que le paiement a réellement été reçu ?',cancel:'Annuler cette commande ? Le stock sera restitué. Cette action est définitive.'};
    if (!window.confirm(confirmations[action])) return;
    busy = true;
    const buttons = forms.flatMap(item => [...item.querySelectorAll('button[type="submit"]')]);
    buttons.forEach(button => { button.disabled = true; });
    form.setAttribute('aria-busy','true'); message.textContent = 'Enregistrement en cours…';
    try {
      const response = await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:data});
      let result;
      try { result = await response.json(); } catch (_) { throw new Error('Réponse illisible. Rechargez la commande avant de réessayer.'); }
      if (!response.ok || result?.success !== true) throw new Error(result?.error || 'Action impossible. Rechargez la commande pour vérifier son état.');
      const text = typeof result.message === 'string' ? result.message : (action === 'ship' ? `La commande a été confiée à ${courier} et mise en livraison.` : labels[action]);
      try { sessionStorage.setItem(storageKey,JSON.stringify({reference:form.dataset.orderReference,text,time:Date.now()})); } catch (_) {}
      message.textContent = text;
      window.location.reload();
    } catch (error) {
      message.textContent = error?.message || 'Connexion interrompue. Rechargez la commande avant de réessayer.';
      busy = false; buttons.forEach(button => { button.disabled = false; }); form.removeAttribute('aria-busy');
    }
  }));
})();
