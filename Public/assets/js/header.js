// Gère uniquement le menu mobile et l'état sticky du Header.
const header = document.querySelector('[data-header]');

if (header) {
  const menu = header.querySelector('[data-mobile-menu]');
  const openButton = header.querySelector('[data-menu-open]');
  const closeButton = header.querySelector('[data-menu-close]');
  const overlay = header.querySelector('[data-menu-overlay]');
  const categoriesTrigger = header.querySelector('[data-categories-trigger]');
  const categoryNavigation = header.querySelector('[data-category-navigation]');
  const mobileBreakpoint = window.matchMedia('(max-width: 48rem)');
  // Referme le panneau Catégories et remet son état accessible à zéro.
  const resetCategoryNavigation = () => {
    if (categoryNavigation) categoryNavigation.hidden = true;
    categoriesTrigger?.setAttribute('aria-expanded', 'false');
  };

  // Ferme le drawer mobile et rend éventuellement le focus au bouton d'ouverture.
  const closeMenu = (restoreFocus = true) => {
    header.classList.remove('is-menu-open');
    document.body.classList.remove('menu-open');
    openButton.setAttribute('aria-expanded', 'false');
    overlay.setAttribute('aria-hidden', 'true');
    resetCategoryNavigation();

    if (restoreFocus) {
      openButton.focus();
    }
  };

  // Ouvre le drawer mobile et place le focus sur son bouton de fermeture.
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
      return;
    }

    if (event.key === 'Tab' && header.classList.contains('is-menu-open')) {
      const focusableElements = [...menu.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement?.focus();
      } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement?.focus();
      }
    }
  });

  mobileBreakpoint.addEventListener('change', (event) => {
    if (!event.matches) {
      closeMenu(false);
      return;
    }

    resetCategoryNavigation();
  });

  let scrollUpdatePending = false;
  let hasLeftPageTop = window.scrollY > 96;

  // Met à jour le Header une seule fois par frame pendant le défilement.
  const updateStickyState = () => {
    const scrollPosition = window.scrollY;

    header.classList.toggle('is-sticky', scrollPosition > 0);

    if (scrollPosition > 96) {
      hasLeftPageTop = true;
    } else if (scrollPosition <= 1 && hasLeftPageTop) {
      hasLeftPageTop = false;
      window.MotionSystem?.headerIn(header);
    }

    scrollUpdatePending = false;
  };

  window.addEventListener('scroll', () => {
    if (!scrollUpdatePending) {
      window.requestAnimationFrame(updateStickyState);
      scrollUpdatePending = true;
    }
  }, { passive: true });

  updateStickyState();

  if (window.scrollY <= 1) {
    window.MotionSystem?.headerIn(header);
  }
}
