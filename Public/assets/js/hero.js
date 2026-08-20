(() => {
  const slider = document.querySelector("[data-hero-slider]");

  if (!slider || typeof window.Swiper !== "function") {
    return;
  }

  const hero = slider.closest("[data-hero]");
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (!hero) {
    return;
  }

  const animateActiveSlide = (swiper) => {
    const activeSlide = swiper.slides[swiper.activeIndex];
    window.MotionSystem?.heroSlide(activeSlide);
  };

  if (!reducedMotion && typeof window.gsap === "object") {
    hero.classList.add("has-gsap");
  }

  new window.Swiper(slider, {
    slidesPerView: 1,
    loop: true,
    speed: reducedMotion ? 0 : 800,
    effect: reducedMotion ? "slide" : "fade",
    fadeEffect: {
      crossFade: true,
    },
    autoplay: reducedMotion
      ? false
      : {
          delay: 5500,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        },
    navigation: {
      prevEl: hero.querySelector(".hero-navigation__previous"),
      nextEl: hero.querySelector(".hero-navigation__next"),
    },
    pagination: {
      el: hero.querySelector(".hero-pagination"),
      clickable: true,
    },
    keyboard: {
      enabled: true,
      onlyInViewport: true,
    },
    a11y: {
      enabled: true,
      prevSlideMessage: "Diapositive précédente",
      nextSlideMessage: "Diapositive suivante",
      paginationBulletMessage: "Aller à la diapositive {{index}}",
    },
    on: {
      init: animateActiveSlide,
      slideChangeTransitionStart: animateActiveSlide,
    },
  });
})();
