// Pilote les suggestions de recherche accessibles du Header.
(() => {
  const form = document.querySelector("[data-header-search]");

  if (!form) {
    return;
  }

  const input = form.querySelector("input[type='search']");
  const suggestionsPanel = form.querySelector("[data-search-suggestions]");
  let suggestions = [];
  let activeIndex = -1;
  let searchTimer;
  let requestController;
  const historyKey = "tout-y-est:recent-searches";

  // Ajoute une mise en évidence sans injecter de HTML dans la page.
  const appendHighlightedText = (element, text, query) => {
    const normalizedText = text.toLocaleLowerCase("fr");
    const normalizedQuery = query.trim().toLocaleLowerCase("fr");
    const matchIndex = normalizedQuery === "" ? -1 : normalizedText.indexOf(normalizedQuery);

    if (matchIndex < 0) {
      element.textContent = text;
      return;
    }

    const highlight = document.createElement("mark");
    highlight.textContent = text.slice(matchIndex, matchIndex + query.trim().length);
    element.append(
      document.createTextNode(text.slice(0, matchIndex)),
      highlight,
      document.createTextNode(text.slice(matchIndex + query.trim().length)),
    );
  };

  // Lit l'historique local sans bloquer la recherche si le stockage est refusé.
  const recentSearches = () => {
    try {
      const value = JSON.parse(localStorage.getItem(historyKey) || "[]");
      return Array.isArray(value) ? value.filter((item) => typeof item === "string").slice(0, 5) : [];
    } catch {
      return [];
    }
  };

  // Mémorise uniquement les dernières recherches réellement utiles.
  const rememberSearch = (query) => {
    const normalized = query.trim();
    if (normalized.length < 2) return;

    try {
      const searches = [normalized, ...recentSearches().filter((item) => item.toLowerCase() !== normalized.toLowerCase())].slice(0, 5);
      localStorage.setItem(historyKey, JSON.stringify(searches));
    } catch {
      return;
    }
  };

  // Referme la liste et réinitialise son état pour le clavier.
  const closeSuggestions = () => {
    suggestionsPanel.hidden = true;
    suggestionsPanel.replaceChildren();
    suggestionsPanel.setAttribute("role", "listbox");
    suggestionsPanel.setAttribute("aria-busy", "false");
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
    activeIndex = -1;
  };

  // Déplace la sélection visuelle et accessible dans la liste.
  const selectSuggestion = (index) => {
    activeIndex = Math.max(0, Math.min(suggestions.length - 1, index));

    suggestionsPanel.querySelectorAll("[role='option']").forEach((option, optionIndex) => {
      const active = optionIndex === activeIndex;
      option.classList.toggle("is-active", active);
      option.setAttribute("aria-selected", String(active));

      if (active) {
        input.setAttribute("aria-activedescendant", option.id);
        option.scrollIntoView({ block: "nearest" });
      }
    });
  };

  // Construit une suggestion avec des nœuds sûrs plutôt qu'avec du HTML injecté.
  const createSuggestion = (product, index, query) => {
    const link = document.createElement("a");
    const image = document.createElement("img");
    const information = document.createElement("span");
    const name = document.createElement("strong");
    const details = document.createElement("span");

    link.id = `header-search-option-${index}`;
    link.className = "header-search__suggestion";
    link.href = product.url;
    link.setAttribute("role", "option");
    link.setAttribute("aria-selected", "false");
    image.src = product.image;
    image.alt = product.alt;
    image.width = 56;
    image.height = 56;
    image.loading = "lazy";
    information.className = "header-search__suggestion-info";
    appendHighlightedText(name, product.name, query);
    appendHighlightedText(details, product.category, query);
    details.append(document.createTextNode(` · ${product.price}`));
    information.append(name, details);
    link.append(image, information);

    return link;
  };

  // Affiche les produits reçus ou un message clair si aucun ne correspond.
  const renderSuggestions = (products, query) => {
    suggestions = products;
    activeIndex = -1;
    suggestionsPanel.replaceChildren();
    suggestionsPanel.setAttribute("aria-busy", "false");

    if (products.length === 0) {
      const emptyMessage = document.createElement("p");
      emptyMessage.className = "header-search__empty header-search__status";
      emptyMessage.textContent = "Aucun produit trouvé. Essayez un nom, une marque ou une catégorie.";
      suggestionsPanel.setAttribute("role", "status");
      suggestionsPanel.append(emptyMessage);
    } else {
      suggestionsPanel.setAttribute("role", "listbox");
      products.forEach((product, index) => suggestionsPanel.append(createSuggestion(product, index, query)));
    }

    suggestionsPanel.hidden = false;
    input.setAttribute("aria-expanded", "true");
  };

  // Informe discrètement l'utilisateur pendant une recherche ou après une erreur.
  const renderSearchStatus = (message, state) => {
    suggestions = [];
    activeIndex = -1;
    const status = document.createElement("p");
    status.className = "header-search__status";
    status.dataset.state = state;
    status.textContent = message;
    suggestionsPanel.replaceChildren(status);
    suggestionsPanel.setAttribute("role", "status");
    suggestionsPanel.setAttribute("aria-busy", String(state === "loading"));
    suggestionsPanel.hidden = false;
    input.setAttribute("aria-expanded", "true");
    input.removeAttribute("aria-activedescendant");
  };

  // Propose l'historique lorsque le champ ne contient pas encore de recherche.
  const renderRecentSearches = () => {
    const searches = recentSearches();

    if (searches.length === 0) {
      closeSuggestions();
      return;
    }

    suggestions = searches.map((query) => ({ url: `/boutique?q=${encodeURIComponent(query)}` }));
    activeIndex = -1;
    suggestionsPanel.setAttribute("role", "listbox");
    suggestionsPanel.setAttribute("aria-busy", "false");
    const title = document.createElement("p");
    title.className = "header-search__recent-title";
    title.textContent = "Recherches récentes";
    suggestionsPanel.replaceChildren(title);

    searches.forEach((query, index) => {
      const link = document.createElement("a");
      const icon = document.createElement("i");
      link.id = `header-search-option-${index}`;
      link.className = "header-search__recent";
      link.href = suggestions[index].url;
      link.setAttribute("role", "option");
      link.setAttribute("aria-selected", "false");
      icon.dataset.lucide = "history";
      icon.setAttribute("aria-hidden", "true");
      link.append(icon, document.createTextNode(query));
      suggestionsPanel.append(link);
    });

    suggestionsPanel.hidden = false;
    input.setAttribute("aria-expanded", "true");
    window.lucide?.createIcons();
  };

  // Annule la requête précédente pour éviter d'afficher une réponse devenue obsolète.
  const search = async (query) => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    renderSearchStatus("Recherche en cours…", "loading");

    try {
      const response = await fetch(`/api/recherche?q=${encodeURIComponent(query)}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error("Search unavailable");
      }

      const payload = await response.json();

      if (input.value.trim() === query) {
        renderSuggestions(payload.suggestions, query);
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        renderSearchStatus("La recherche est momentanément indisponible.", "error");
      }
    } finally {
      if (requestController === controller) requestController = null;
    }
  };

  input.addEventListener("input", () => {
    window.clearTimeout(searchTimer);
    requestController?.abort();
    const query = input.value.trim();

    if (query.length < 2) {
      renderRecentSearches();
      return;
    }

    renderSearchStatus("Recherche en cours…", "loading");
    searchTimer = window.setTimeout(() => search(query), 240);
  });

  input.addEventListener("focus", () => {
    if (input.value.trim() === "") renderRecentSearches();
  });

  form.addEventListener("submit", () => rememberSearch(input.value));

  suggestionsPanel.addEventListener("click", (event) => {
    if (event.target.closest("a")) rememberSearch(input.value);
  });

  // Permet de parcourir et choisir les suggestions sans utiliser la souris.
  input.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !suggestionsPanel.hidden) {
      closeSuggestions();
      return;
    }

    if (suggestionsPanel.hidden || suggestions.length === 0) {
      return;
    }

    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      const direction = event.key === "ArrowDown" ? 1 : -1;
      selectSuggestion((activeIndex + direction + suggestions.length) % suggestions.length);
    } else if (event.key === "Enter" && activeIndex >= 0) {
      event.preventDefault();
      window.location.assign(suggestions[activeIndex].url);
    }
  });

  document.addEventListener("click", (event) => {
    if (!form.contains(event.target)) {
      closeSuggestions();
    }
  });
})();
