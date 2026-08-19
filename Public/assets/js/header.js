const header = document.querySelector('[data-header]');

if (header) {
  const menu = header.querySelector('[data-mobile-menu]');
  const openButton = header.querySelector('[data-menu-open]');
  const closeButton = header.querySelector('[data-menu-close]');
  const overlay = header.querySelector('[data-menu-overlay]');
  const mobileBreakpoint = window.matchMedia('(max-width: 48rem)');

  const closeMenu = (restoreFocus = true) => {
    header.classList.remove('is-menu-open');
    document.body.classList.remove('menu-open');
    openButton.setAttribute('aria-expanded', 'false');
    overlay.setAttribute('aria-hidden', 'true');

    if (restoreFocus) {
      openButton.focus();
    }
  };

  const openMenu = () => {
    header.classList.add('is-menu-open');
    document.body.classList.add('menu-open');
    openButton.setAttribute('aria-expanded', 'true');
    overlay.setAttribute('aria-hidden', 'false');
    closeButton.focus();
  };

  openButton.addEventListener('click', openMenu);
  closeButton.addEventListener('click', () => closeMenu());
  overlay.addEventListener('click', () => closeMenu());

  menu.addEventListener('click', (event) => {
    if (event.target.closest('a') && mobileBreakpoint.matches) {
      closeMenu(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && header.classList.contains('is-menu-open')) {
      closeMenu();
    }
  });

  mobileBreakpoint.addEventListener('change', (event) => {
    if (!event.matches) {
      closeMenu(false);
    }
  });

  let scrollUpdatePending = false;

  const updateStickyState = () => {
    header.classList.toggle('is-sticky', window.scrollY > 0);
    scrollUpdatePending = false;
  };

  window.addEventListener('scroll', () => {
    if (!scrollUpdatePending) {
      window.requestAnimationFrame(updateStickyState);
      scrollUpdatePending = true;
    }
  }, { passive: true });

  updateStickyState();
}
