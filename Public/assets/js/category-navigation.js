// Construit le méga menu à partir des catégories fournies par l'API.
(() => {
  const trigger = document.querySelector("[data-categories-trigger]");
  const navigation = document.querySelector("[data-category-navigation]");

  if (!trigger || !navigation) {
    return;
  }

  const status = navigation.querySelector("[data-category-navigation-status]");
  const content = navigation.querySelector("[data-category-navigation-content]");
  const roots = navigation.querySelector("[data-category-roots]");
  const highlights = navigation.querySelector("[data-category-highlights]");
  const highlightSection = navigation.querySelector(".category-navigation__highlights");
  const highlightStatus = navigation.querySelector("[data-category-highlights-status]");
  const mobileNavigation = window.matchMedia("(max-width: 48rem)");
  const highlightCache = new Map();
  let loaded = false;
  let loading = false;
  let highlightRequestController = null;
  let hoverTimer = null;

  // Crée un lien de catégorie sans injecter de HTML non fiable.
  const createLink = (category) => {
    const link = document.createElement("a");
    link.href = category.url;
    link.textContent = category.name;

    if (category.count !== null) {
      const count = document.createElement("span");
      count.textContent = String(category.count);
      count.setAttribute("aria-label", `${category.count} produits`);
      link.append(count);
    }

    return link;
  };

  // Active une catégorie et affiche ses enfants selon le support utilisé.
  const selectCategory = (item, focusFirstChild = false) => {
    const collapse = mobileNavigation.matches && item.classList.contains("is-active") && !focusFirstChild;

    roots.querySelectorAll("[data-category-item]").forEach((candidate) => {
      const active = candidate === item && !collapse;
      candidate.classList.toggle("is-active", active);
      candidate.querySelector(":scope > button")?.setAttribute("aria-expanded", String(active));
      const children = candidate.querySelector(":scope > [data-category-children]");
      if (children) children.hidden = !active;
    });

    if (focusFirstChild) {
      item.querySelector("[data-category-children] a")?.focus();
    }

    if (!collapse && item.dataset.categorySlug) {
      loadHighlights(item.dataset.categorySlug);
    }
  };

  // Transforme une catégorie reçue en élément accessible du menu.
  const createCategory = (category, index) => {
    const item = document.createElement("li");
    const button = document.createElement("button");
    const label = document.createElement("span");
    const icon = document.createElement("i");
    const children = document.createElement("div");
    const childList = document.createElement("ul");
    const allItem = document.createElement("li");

    item.dataset.categoryItem = "";
    item.dataset.categorySlug = category.slug;
    item.classList.toggle("is-active", index === 0);
    button.type = "button";
    button.setAttribute("aria-expanded", String(index === 0));
    label.textContent = category.name;
    icon.dataset.lucide = "chevron-right";
    icon.setAttribute("aria-hidden", "true");
    button.append(label, icon);
    children.dataset.categoryChildren = "";
    children.hidden = index !== 0;

    allItem.append(createLink({ ...category, name: `Tous les produits ${category.name}` }));
    childList.append(allItem);
    category.children.forEach((child) => {
      const childItem = document.createElement("li");
      childItem.append(createLink(child));
      childList.append(childItem);
    });
    children.append(childList);
    item.append(button, children);
    button.addEventListener("click", () => selectCategory(item));
    button.addEventListener("focus", () => {
      if (!mobileNavigation.matches) selectCategory(item);
    });
    item.addEventListener("mouseenter", () => {
      if (mobileNavigation.matches) return;
      window.clearTimeout(hoverTimer);
      hoverTimer = window.setTimeout(() => selectCategory(item), 120);
    });
    item.addEventListener("mouseleave", () => window.clearTimeout(hoverTimer));
    button.addEventListener("keydown", (event) => {
      if (event.key === "ArrowRight") selectCategory(item, true);
    });

    return item;
  };

  // Crée la carte compacte d'un produit mis en avant.
  const createHighlight = (product) => {
    const link = document.createElement("a");
    const image = document.createElement("img");
    const information = document.createElement("span");
    const label = document.createElement("small");
    const name = document.createElement("strong");

    link.href = product.url;
    image.src = product.image;
    image.alt = product.alt;
    image.width = 96;
    image.height = 96;
    image.loading = "lazy";
    label.textContent = product.label;
    name.textContent = product.name;
    information.append(label, name);
    link.append(image, information);

    return link;
  };

  // Remplace les mises en avant et masque leur zone lorsqu'elle est vide.
  const renderHighlights = (products) => {
    highlights.replaceChildren(...products.map(createHighlight));
    highlightSection.hidden = products.length === 0;
    highlightSection.setAttribute("aria-busy", "false");
    window.lucide?.createIcons();
  };

  // Affiche le contenu initial reçu depuis l'API de navigation.
  const render = (payload) => {
    roots.replaceChildren(...payload.categories.map(createCategory));
    const firstCategory = payload.categories[0];
    if (firstCategory) highlightCache.set(firstCategory.slug, payload.highlights);
    renderHighlights(payload.highlights);
    status.hidden = true;
    content.hidden = false;
    window.lucide?.createIcons();
    loaded = true;
  };

  // Charge les mises en avant d'une catégorie et évite les requêtes répétées.
  const loadHighlights = async (categorySlug) => {
    if (highlightCache.has(categorySlug)) {
      renderHighlights(highlightCache.get(categorySlug));
      return;
    }

    highlightRequestController?.abort();
    const controller = new AbortController();
    highlightRequestController = controller;
    highlightSection.hidden = false;
    highlightSection.setAttribute("aria-busy", "true");
    highlightStatus.textContent = "Mise à jour des sélections…";

    try {
      const parameters = new URLSearchParams({ category: categorySlug });
      const response = await fetch(`/api/navigation/highlights?${parameters}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      if (!response.ok) throw new Error("Sélections indisponibles");
      const payload = await response.json();
      highlightCache.set(categorySlug, payload.highlights);
      renderHighlights(payload.highlights);
      highlightStatus.textContent = "Sélections mises à jour.";
    } catch (error) {
      if (error.name !== "AbortError") {
        highlightSection.setAttribute("aria-busy", "false");
        highlightStatus.textContent = "Les sélections sont momentanément indisponibles.";
      }
    } finally {
      if (highlightRequestController === controller) highlightRequestController = null;
    }
  };

  // Charge une seule fois la hiérarchie complète des catégories.
  const load = async () => {
    if (loaded || loading) return;
    loading = true;

    try {
      const response = await fetch("/api/navigation/categories", { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Navigation indisponible");
      render(await response.json());
    } catch {
      status.textContent = "Les catégories sont momentanément indisponibles.";
    } finally {
      loading = false;
    }
  };

  // Ferme le panneau et rend éventuellement le focus à son déclencheur.
  const close = (restoreFocus = false) => {
    navigation.hidden = true;
    trigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) trigger.focus();
  };

  // Ouvre le panneau, attend ses données puis gère le focus clavier.
  const open = async (focusFirstCategory = false) => {
    navigation.hidden = false;
    trigger.setAttribute("aria-expanded", "true");
    await load();

    if (focusFirstCategory) {
      roots.querySelector("[data-category-item] > button")?.focus();
    }
  };

  trigger.addEventListener("click", () => navigation.hidden ? open() : close());
  trigger.addEventListener("keydown", (event) => {
    if (event.key !== "ArrowDown") return;
    event.preventDefault();
    open(true);
  });
  document.addEventListener("click", (event) => {
    if (!navigation.hidden && !navigation.contains(event.target) && !trigger.contains(event.target)) close();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !navigation.hidden) close(true);
  });
})();
