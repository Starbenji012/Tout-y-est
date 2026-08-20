(() => {
  const STORAGE_KEY = "tout-y-est:favorites";

  const normalize = (values) => [...new Set(
    (Array.isArray(values) ? values : [])
      .map(Number)
      .filter((value) => Number.isInteger(value) && value > 0),
  )].slice(0, 40);

  const read = () => {
    try {
      return normalize(JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]"));
    } catch {
      return [];
    }
  };

  const updateHeader = (ids) => {
    const count = ids.length;
    const label = `Favoris, ${count} article${count > 1 ? "s" : ""}`;

    document.querySelectorAll("[data-favorites-count]").forEach((badge) => {
      badge.textContent = String(count);
    });
    document.querySelectorAll("[data-favorites-link]").forEach((link) => {
      link.setAttribute("aria-label", label);
    });
  };

  const write = (values) => {
    const ids = normalize(values);

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch {
      return read();
    }

    updateHeader(ids);
    window.dispatchEvent(new CustomEvent("favorites:updated", { detail: { ids } }));
    return ids;
  };

  window.FavoriteStore = Object.freeze({
    ids: read,
    has: (productId) => read().includes(Number(productId)),
    replace: write,
    toggle: (productId) => {
      const normalizedId = Number(productId);

      if (!Number.isInteger(normalizedId) || normalizedId < 1) {
        return false;
      }

      const ids = read();
      const active = !ids.includes(normalizedId);
      const storedIds = write(active ? [...ids, normalizedId] : ids.filter((id) => id !== normalizedId));

      return storedIds.includes(normalizedId);
    },
  });

  updateHeader(read());

  window.addEventListener("storage", (event) => {
    if (event.key !== STORAGE_KEY) {
      return;
    }

    const ids = read();
    updateHeader(ids);
    window.dispatchEvent(new CustomEvent("favorites:updated", { detail: { ids } }));
  });
})();
