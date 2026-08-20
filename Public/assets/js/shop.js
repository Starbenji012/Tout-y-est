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

  if (!panel || !openButton || !filtersForm || !searchForm || !sortSelect || !results || !content || !loader || !count) {
    return;
  }

  const storageKey = "tout-y-est:catalog-view";
  let previousFocus = null;
  let requestController = null;
  let priceTimer = null;

  const readStoredView = () => {
    try {
      return localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  };

  const storeView = (view) => {
    try {
      localStorage.setItem(storageKey, view);
    } catch {
      return;
    }
  };

  const closeFilters = () => {
    panel.classList.remove("is-open");
    document.body.classList.remove("catalog-filters-open");
    openButton.setAttribute("aria-expanded", "false");
    previousFocus?.focus();
  };

  const openFilters = () => {
    previousFocus = document.activeElement;
    panel.classList.add("is-open");
    document.body.classList.add("catalog-filters-open");
    openButton.setAttribute("aria-expanded", "true");
    panel.querySelector("[data-catalog-filter-close]")?.focus();
  };

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

  const setLoading = (isLoading) => {
    loader.hidden = !isLoading;
    results.classList.toggle("is-loading", isLoading);
    results.setAttribute("aria-busy", String(isLoading));
  };

  const refreshEnhancements = () => {
    window.lucide?.createIcons();
    window.AOS?.refreshHard();
  };

  const updateCatalog = async (page = 1) => {
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
      content.innerHTML = catalog.html;
      count.innerHTML = `<strong>${catalog.count}</strong> produits trouvés`;
      window.history.replaceState({}, "", `/boutique?${parameters.toString()}`);
      refreshEnhancements();

      if (window.matchMedia("(max-width: 64rem)").matches) {
        closeFilters();
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        window.Swal?.fire({ toast: true, position: "bottom-end", icon: "error", title: "Impossible de mettre à jour le catalogue", showConfirmButton: false, timer: 2600 });
      }
    } finally {
      if (requestController === controller) {
        setLoading(false);
      }
    }
  };

  openButton.addEventListener("click", openFilters);
  closeButtons.forEach((button) => button.addEventListener("click", closeFilters));
  viewButtons.forEach((button) => button.addEventListener("click", () => applyView(button.dataset.catalogView)));
  searchForm.addEventListener("submit", (event) => {
    event.preventDefault();
    updateCatalog();
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
    updateCatalog(Number(pageLink.dataset.page) || 1);
    results.scrollIntoView({ behavior: "smooth", block: "start" });
  });
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
      closeFilters();
    }
  });

  applyView(readStoredView() || "grid");
})();
