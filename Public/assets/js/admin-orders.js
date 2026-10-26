// Présentation du filtre seulement : la soumission reste un GET classique.
(() => {
  document.querySelectorAll('[data-select-control]').forEach(select => window.SelectControl?.enhance(select));
})();
