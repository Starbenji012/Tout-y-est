// Orchestre l'interface du catalogue sans porter les règles métier des produits.
(() => {
  const panel = document.querySelector("[data-catalog-filters-panel]");
  const openButton = document.querySelector("[data-catalog-filter-open]");
  const closeButtons = document.querySelectorAll("[data-catalog-filter-close]");
  const filtersForm = document.querySelector("[data-catalog-filters]");
  const searchForm = document.querySelector("[data-catalog-search]");
  const sortSelect = document.querySelector("[data-catalog-sort]");
  const results = document.querySelector("[data-catalog-results]");
  const content = document.querySelector("[data-catalog-content]");
  const loader = document.querySelector("[data-catalog-loader]");
  const count = document.querySelector("[data-catalog-count]");
  const viewButtons = document.querySelectorAll("[data-catalog-view]");
  const contextFilters = document.querySelector("[data-catalog-context-filters]");
  const searchNotice = document.querySelector("[data-catalog-search-notice]");
  const breadcrumb = document.querySelector("[data-catalog-breadcrumb]");
  const searchInput = searchForm?.querySelector("input[type='search']");

  if (!panel || !openButton || !filtersForm || !searchForm || !sortSelect || !results || !content || !loader || !count) {
    return;
  }

  const storageKey = "tout-y-est:catalog-view";
  let previousFocus = null;
  let requestController = null;
  let priceTimer = null;

  // Récupère la préférence d'affichage sans dépendre du stockage local.
  const readStoredView = () => {
    try {
      return localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  };

  // Conserve le choix grille ou liste pour la prochaine visite.
  const storeView = (view) => {
    try {
      localStorage.setItem(storageKey, view);
    } catch {
      return;
    }
  };

  // Referme le panneau mobile et rend le focus à son déclencheur.
  const closeFilters = (restoreFocus = true) => {
    panel.classList.remove("is-open");
    document.body.classList.remove("catalog-filters-open");
    openButton.setAttribute("aria-expanded", "false");
    panel.removeAttribute("aria-modal");
    panel.removeAttribute("role");

    if (restoreFocus && previousFocus?.isConnected) {
      previousFocus.focus();
    }
  };

  // Présente les filtres comme une boîte de dialogue sur petit écran.
  const openFilters = () => {
    previousFocus = document.activeElement;
    panel.classList.add("is-open");
    document.body.classList.add("catalog-filters-open");
    openButton.setAttribute("aria-expanded", "true");
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-modal", "true");
    panel.querySelector("[data-catalog-filter-close]")?.focus();
  };

  // Change uniquement la présentation : les cartes produit restent identiques.
  const applyView = (view) => {
    const normalizedView = view === "list" ? "list" : "grid";
    results.classList.toggle("is-list-view", normalizedView === "list");
    viewButtons.forEach((button) => {
      const active = button.dataset.catalogView === normalizedView;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", String(active));
    });
    storeView(normalizedView);
  };

  // Réunit les critères courants dans le format attendu par l'API.
  const buildParameters = (page = 1) => {
    const parameters = new URLSearchParams(new FormData(filtersForm));
    const search = new FormData(searchForm).get("q");

    if (search) {
      parameters.set("q", String(search));
    }

    parameters.set("sort", sortSelect.value);
    parameters.set("page", String(page));
    return parameters;
  };

  // Rend le chargement perceptible et compréhensible par les aides techniques.
  const setLoading = (isLoading) => {
    loader.hidden = !isLoading;
    results.classList.toggle("is-loading", isLoading);
    results.setAttribute("aria-busy", String(isLoading));
  };

  // Réactive les icônes et animations après un remplacement dynamique du HTML.
  const refreshEnhancements = () => {
    window.lucide?.createIcons();
    window.MotionSystem?.refresh(content);
  };

  // Ajoute une page de produits sans recréer toute la grille existante.
  const appendCatalog = (html) => {
    const template = document.createElement("template");
    template.innerHTML = html.trim();
    const currentGrid = content.querySelector(".product-section__grid");
    const nextGrid = template.content.querySelector(".product-section__grid");

    if (!currentGrid || !nextGrid) {
      content.innerHTML = html;
      return;
    }

    currentGrid.append(...nextGrid.children);
    content.querySelector(".product-section__pagination")?.remove();
    const nextPagination = template.content.querySelector(".product-section__pagination");
    if (nextPagination) content.append(nextPagination);
  };

  // Demande le catalogue filtré et ignore proprement toute réponse dépassée.
  const updateCatalog = async (page = 1, append = false) => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const parameters = buildParameters(page);
    setLoading(true);

    try {
      const response = await fetch(`/api/catalogue?${parameters.toString()}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error("Catalogue indisponible");
      }

      const catalog = await response.json();
      if (append) {
        appendCatalog(catalog.html);
      } else {
        content.innerHTML = catalog.html;
      }
      if (!append && contextFilters && typeof catalog.facetsHtml === "string") {
        contextFilters.innerHTML = catalog.facetsHtml;
      }
      if (!append && breadcrumb && typeof catalog.breadcrumbHtml === "string") {
        breadcrumb.innerHTML = catalog.breadcrumbHtml;
      }
      const resultLabel = catalog.count === 1 ? "produit trouvé" : "produits trouvés";
      count.innerHTML = `<strong>${catalog.count}</strong> ${resultLabel}`;
      if (searchNotice) {
        searchNotice.textContent = catalog.searchNotice || "";
        searchNotice.hidden = !catalog.searchNotice;
      }
      window.history.replaceState({}, "", `/boutique?${parameters.toString()}`);
      refreshEnhancements();

      if (window.matchMedia("(max-width: 64rem)").matches) {
        closeFilters(false);
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        window.MotionSystem?.fire({ toast: true, position: "bottom-end", icon: "error", title: "Impossible de mettre à jour le catalogue", showConfirmButton: false, timer: 2600 });
      }
    } finally {
      if (requestController === controller) {
        setLoading(false);
      }
    }
  };

  openButton.addEventListener("click", openFilters);
  closeButtons.forEach((button) => button.addEventListener("click", () => closeFilters()));
  viewButtons.forEach((button) => button.addEventListener("click", () => applyView(button.dataset.catalogView)));
  searchForm.addEventListener("submit", (event) => {
    event.preventDefault();
    updateCatalog();
  });
  searchInput?.addEventListener("input", () => {
    window.clearTimeout(priceTimer);
    priceTimer = window.setTimeout(() => updateCatalog(), 320);
  });
  sortSelect.addEventListener("change", () => updateCatalog());
  filtersForm.addEventListener("change", (event) => {
    if (event.target.matches('[name="price_min"], [name="price_max"]')) {
      window.clearTimeout(priceTimer);
      priceTimer = window.setTimeout(() => updateCatalog(), 350);
      return;
    }
    updateCatalog();
  });
  filtersForm.addEventListener("reset", () => window.requestAnimationFrame(() => updateCatalog()));
  content.addEventListener("click", (event) => {
    const pageLink = event.target.closest("[data-page]");
    if (!pageLink || pageLink.getAttribute("aria-disabled") === "true") {
      return;
    }
    event.preventDefault();
    const page = Number(pageLink.dataset.page) || 1;
    const loadMore = pageLink.matches("[data-load-more]");
    updateCatalog(page, loadMore);
    if (!loadMore) results.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  // Garde la navigation clavier à l'intérieur du panneau mobile ouvert.
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && panel.classList.contains("is-open")) {
      closeFilters();
    }

    if (event.key === "Tab" && panel.classList.contains("is-open")) {
      const focusableElements = [...panel.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), [href]')];
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
  window.matchMedia("(min-width: 64.0625rem)").addEventListener("change", (event) => {
    if (event.matches && panel.classList.contains("is-open")) {
      closeFilters(false);
    }
  });

  applyView(readStoredView() || "grid");
})();
