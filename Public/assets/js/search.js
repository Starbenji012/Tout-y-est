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
  const createSuggestion = (product, index) => {
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
    name.textContent = product.name;
    details.textContent = `${product.category} · ${product.price}`;
    information.append(name, details);
    link.append(image, information);

    return link;
  };

  // Affiche les produits reçus ou un message clair si aucun ne correspond.
  const renderSuggestions = (products) => {
    suggestions = products;
    activeIndex = -1;
    suggestionsPanel.replaceChildren();

    if (products.length === 0) {
      const emptyMessage = document.createElement("p");
      emptyMessage.className = "header-search__empty";
      emptyMessage.textContent = "Aucun produit correspondant.";
      emptyMessage.setAttribute("role", "option");
      emptyMessage.setAttribute("aria-disabled", "true");
      suggestionsPanel.append(emptyMessage);
    } else {
      products.forEach((product, index) => suggestionsPanel.append(createSuggestion(product, index)));
    }

    suggestionsPanel.hidden = false;
    input.setAttribute("aria-expanded", "true");
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
        renderSuggestions(payload.suggestions);
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        closeSuggestions();
      }
    }
  };

  input.addEventListener("input", () => {
    window.clearTimeout(searchTimer);
    const query = input.value.trim();

    if (query.length < 2) {
      requestController?.abort();
      renderRecentSearches();
      return;
    }

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
    } else if (event.key === "Escape") {
      closeSuggestions();
    }
  });

  document.addEventListener("click", (event) => {
    if (!form.contains(event.target)) {
      closeSuggestions();
    }
  });
})();
