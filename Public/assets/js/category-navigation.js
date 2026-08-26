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
  const mobileNavigation = window.matchMedia("(max-width: 48rem)");
  let loaded = false;
  let loading = false;

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
  };

  const createCategory = (category, index) => {
    const item = document.createElement("li");
    const button = document.createElement("button");
    const label = document.createElement("span");
    const icon = document.createElement("i");
    const children = document.createElement("div");
    const childList = document.createElement("ul");
    const allItem = document.createElement("li");

    item.dataset.categoryItem = "";
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
    button.addEventListener("keydown", (event) => {
      if (event.key === "ArrowRight") selectCategory(item, true);
    });

    return item;
  };

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

  const render = (payload) => {
    roots.replaceChildren(...payload.categories.map(createCategory));
    highlights.replaceChildren(...payload.highlights.map(createHighlight));
    status.hidden = true;
    content.hidden = false;
    navigation.querySelector(".category-navigation__highlights").hidden = payload.highlights.length === 0;
    window.lucide?.createIcons();
    loaded = true;
  };

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

  const close = (restoreFocus = false) => {
    navigation.hidden = true;
    trigger.setAttribute("aria-expanded", "false");
    if (restoreFocus) trigger.focus();
  };

  const open = () => {
    navigation.hidden = false;
    trigger.setAttribute("aria-expanded", "true");
    load();
  };

  trigger.addEventListener("click", () => navigation.hidden ? open() : close());
  document.addEventListener("click", (event) => {
    if (!navigation.hidden && !navigation.contains(event.target) && !trigger.contains(event.target)) close();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !navigation.hidden) close(true);
  });
})();
