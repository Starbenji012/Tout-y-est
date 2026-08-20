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

  const closeSuggestions = () => {
    suggestionsPanel.hidden = true;
    suggestionsPanel.replaceChildren();
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
    activeIndex = -1;
  };

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
      closeSuggestions();
      return;
    }

    searchTimer = window.setTimeout(() => search(query), 240);
  });

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
