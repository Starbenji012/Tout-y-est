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

  const usesGsap = !reducedMotion && typeof window.gsap === "object";

  const animateActiveSlide = (swiper) => {
    if (!usesGsap) {
      return;
    }

    const activeSlide = swiper.slides[swiper.activeIndex];
    const content = activeSlide?.querySelectorAll(".hero-left > *");
    const image = activeSlide?.querySelector(".hero-product-image");

    if (!activeSlide || !content || !image) {
      return;
    }

    window.gsap.killTweensOf([...content, image]);
    window.gsap.fromTo(
      content,
      { autoAlpha: 0, y: 18 },
      {
        autoAlpha: 1,
        y: 0,
        duration: 0.65,
        stagger: 0.08,
        ease: "power3.out",
        clearProps: "opacity,transform,visibility",
      },
    );
    window.gsap.fromTo(
      image,
      { scale: 1.08 },
      { scale: 1.045, duration: 1.2, ease: "power3.out", clearProps: "transform" },
    );
  };

  if (usesGsap) {
    hero.classList.add("has-gsap");
  }

  const swiper = new window.Swiper(slider, {
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

  window.addEventListener("site:return-to-top", () => animateActiveSlide(swiper));
})();
