(() => {
  const reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
  let observer;

  const reveal = (element) => element.classList.add("is-motion-visible");

  const createObserver = () => new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        window.requestAnimationFrame(() => reveal(entry.target));
        return;
      }

      if (!entry.target.hasAttribute("data-motion-once")) {
        entry.target.classList.remove("is-motion-visible");
      }
    });
  }, {
    threshold: 0.08,
    rootMargin: "8% 0px 8% 0px",
  });

  const refresh = (root = document) => {
    const elements = [
      ...(root.matches?.("[data-motion]") ? [root] : []),
      ...root.querySelectorAll("[data-motion]"),
    ];

    elements.forEach((element) => {
      const delay = Math.max(0, Math.min(280, Number(element.dataset.motionDelay) || 0));
      element.style.setProperty("--motion-delay", `${delay}ms`);

      if (reducedMotionQuery.matches || !observer) {
        reveal(element);
      } else {
        observer.observe(element);
      }
    });
  };

  const init = () => {
    document.documentElement.classList.add("motion-enabled");

    if (!reducedMotionQuery.matches && "IntersectionObserver" in window) {
      observer = createObserver();
    }

    refresh();
  };

  const headerIn = (header) => {
    if (reducedMotionQuery.matches) {
      return;
    }

    if (typeof window.gsap === "object") {
      window.gsap.killTweensOf(header);
      window.gsap.fromTo(header, { autoAlpha: 0.97, y: -8 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.56,
        ease: "power2.out",
        clearProps: "opacity,transform,visibility",
      });
      return;
    }

    header.animate(
      [{ opacity: 0.97, transform: "translateY(-8px)" }, { opacity: 1, transform: "translateY(0)" }],
      { duration: 560, easing: "cubic-bezier(0.2, 0.68, 0.24, 1)" },
    );
  };

  const heroSlide = (slide) => {
    if (reducedMotionQuery.matches || !slide) {
      return;
    }

    const content = slide.querySelectorAll(".hero-left > *");
    const image = slide.querySelector(".hero-product-image");

    if (typeof window.gsap === "object") {
      window.gsap.killTweensOf([...content, image].filter(Boolean));
      window.gsap.fromTo(content, { autoAlpha: 0, y: 14 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.78,
        stagger: 0.075,
        ease: "power2.out",
        clearProps: "opacity,transform,visibility",
      });

      if (image) {
        window.gsap.fromTo(image, { scale: 1.065 }, {
          scale: 1.045,
          duration: 1.55,
          ease: "power2.out",
          clearProps: "transform",
        });
      }
    }
  };

  const modalIn = (dialog) => {
    if (reducedMotionQuery.matches || typeof window.gsap !== "object") {
      return;
    }

    window.gsap.fromTo(dialog, { autoAlpha: 0, y: 10, scale: 0.99 }, {
      autoAlpha: 1,
      y: 0,
      scale: 1,
      duration: 0.42,
      ease: "power2.out",
      clearProps: "opacity,transform,visibility",
    });
  };

  const modalOut = (dialog, onComplete) => {
    if (reducedMotionQuery.matches || typeof window.gsap !== "object") {
      onComplete();
      return;
    }

    window.gsap.to(dialog, {
      autoAlpha: 0,
      y: 6,
      scale: 0.995,
      duration: 0.24,
      ease: "power1.inOut",
      onComplete,
    });
  };

  const swapPanels = (currentPanel, nextPanel, onComplete = () => {}) => {
    if (!currentPanel || !nextPanel || currentPanel === nextPanel) {
      onComplete();
      return;
    }

    const container = currentPanel.parentElement;

    if (reducedMotionQuery.matches || typeof window.gsap !== "object") {
      currentPanel.hidden = true;
      nextPanel.hidden = false;
      onComplete();
      return;
    }

    const startHeight = currentPanel.offsetHeight;
    container.style.height = `${startHeight}px`;
    container.style.overflow = "hidden";

    const timeline = window.gsap.timeline({
      defaults: { ease: "power2.out" },
      onComplete: () => {
        container.style.removeProperty("height");
        container.style.removeProperty("overflow");
        onComplete();
      },
    });

    timeline.to(currentPanel, { autoAlpha: 0, y: -6, duration: 0.22 });
    timeline.call(() => {
      currentPanel.hidden = true;
      nextPanel.hidden = false;
      window.gsap.set(nextPanel, { autoAlpha: 0, y: 10 });
      window.gsap.to(container, { height: nextPanel.offsetHeight, duration: 0.42, ease: "power2.inOut" });
    });
    timeline.to(nextPanel, {
      autoAlpha: 1,
      y: 0,
      duration: 0.48,
      clearProps: "opacity,transform,visibility",
    }, "<0.08");
  };

  const fire = (options) => window.Swal?.fire({
    ...options,
    showClass: { popup: options.toast ? "motion-toast-enter" : "motion-modal-enter" },
    hideClass: { popup: options.toast ? "motion-toast-leave" : "motion-modal-leave" },
  });

  window.MotionSystem = Object.freeze({
    init,
    refresh,
    headerIn,
    heroSlide,
    modalIn,
    modalOut,
    swapPanels,
    fire,
  });
})();
