(() => {
  const initializeIcons = () => window.lucide?.createIcons();

  const initializeAnimations = () => {
    if (window.AOS) {
      document.querySelectorAll("[data-aos]").forEach((target) => {
        target.dataset.aosMirror = "true";
        target.dataset.aosOnce = "false";
      });

      window.AOS.init({
        duration: 750,
        easing: "ease-out-cubic",
        offset: 24,
        once: false,
        mirror: true,
        anchorPlacement: "top-bottom",
        disable: () => window.matchMedia("(prefers-reduced-motion: reduce)").matches,
      });
      document.documentElement.classList.add("aos-enabled");

      window.addEventListener("load", () => window.AOS.refreshHard(), { once: true });
    }
  };

  initializeIcons();
  initializeAnimations();
})();
