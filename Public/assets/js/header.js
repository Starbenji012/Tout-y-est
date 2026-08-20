const header = document.querySelector('[data-header]');

if (header) {
  const menu = header.querySelector('[data-mobile-menu]');
  const openButton = header.querySelector('[data-menu-open]');
  const closeButton = header.querySelector('[data-menu-close]');
  const overlay = header.querySelector('[data-menu-overlay]');
  const mobileBreakpoint = window.matchMedia('(max-width: 48rem)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const animateHeader = () => {
    if (reducedMotion || typeof window.gsap !== 'object') {
      return;
    }

    window.gsap.killTweensOf(header);
    window.gsap.fromTo(
      header,
      { autoAlpha: 0.96, y: -12 },
      {
        autoAlpha: 1,
        y: 0,
        duration: 0.65,
        ease: 'power3.out',
        clearProps: 'opacity,transform,visibility',
      },
    );
  };

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
  let hasLeftPageTop = window.scrollY > 96;

  const updateStickyState = () => {
    const scrollPosition = window.scrollY;

    header.classList.toggle('is-sticky', scrollPosition > 0);

    if (scrollPosition > 96) {
      hasLeftPageTop = true;
    } else if (scrollPosition <= 1 && hasLeftPageTop) {
      hasLeftPageTop = false;
      animateHeader();
      window.dispatchEvent(new CustomEvent('site:return-to-top'));
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
    animateHeader();
  }
}
