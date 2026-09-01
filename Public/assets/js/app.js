// Initialise les comportements communs présents sur toutes les pages.
(() => {
  // Remplace les marqueurs Lucide par leurs icônes SVG.
  const initializeIcons = () => window.lucide?.createIcons();

  initializeIcons();
  window.MotionSystem?.init();
})();
