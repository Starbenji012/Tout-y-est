// Sans JS, les liens restent visibles ; sur mobile le bouton pilote une seule navigation.
(() => {
  const button = document.querySelector('[data-admin-menu]');
  const nav = document.getElementById('admin-navigation');
  if (!button || !nav) return;
  button.hidden = false;
  nav.classList.add('is-enhanced');
  const close = () => { button.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); };
  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open)); nav.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') { close(); button.focus(); }
  });
  document.addEventListener('pointerdown', event => { if (!nav.contains(event.target) && !button.contains(event.target)) close(); });
})();
