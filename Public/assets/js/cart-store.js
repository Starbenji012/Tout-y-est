(() => {
  const STORAGE_KEY = "tout-y-est:cart";

  const normalize = (values) => {
    const items = new Map();

    (Array.isArray(values) ? values : []).slice(0, 40).forEach((item) => {
      const id = Number(item?.id);
      const quantity = Math.min(99, Math.max(1, Number(item?.quantity) || 1));

      if (Number.isInteger(id) && id > 0) {
        items.set(id, { id, quantity });
      }
    });

    return [...items.values()];
  };

  const read = () => {
    try {
      return normalize(JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]"));
    } catch {
      return [];
    }
  };

  const updateHeader = (items) => {
    const count = items.reduce((total, item) => total + item.quantity, 0);
    const label = `Panier, ${count} produit${count > 1 ? "s" : ""}`;

    document.querySelectorAll("[data-cart-count]").forEach((badge) => {
      badge.textContent = String(count);
    });
    document.querySelectorAll("[data-cart-link]").forEach((link) => {
      link.setAttribute("aria-label", label);
    });
  };

  const write = (values) => {
    const items = normalize(values);

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      return read();
    }

    updateHeader(items);
    window.dispatchEvent(new CustomEvent("cart:updated", { detail: { items } }));
    return items;
  };

  window.CartStore = Object.freeze({
    items: read,
    replace: write,
    serialize: () => read().map((item) => `${item.id}:${item.quantity}`).join(","),
    add: (productId, quantity = 1) => {
      const id = Number(productId);

      if (!Number.isInteger(id) || id < 1) {
        return read();
      }

      const items = read();
      const current = items.find((item) => item.id === id);

      if (current) {
        current.quantity = Math.min(99, current.quantity + Math.max(1, Number(quantity) || 1));
      } else {
        items.push({ id, quantity });
      }

      return write(items);
    },
    setQuantity: (productId, quantity) => write(read().map((item) => (
      item.id === Number(productId) ? { ...item, quantity } : item
    ))),
    remove: (productId) => write(read().filter((item) => item.id !== Number(productId))),
    clear: () => write([]),
  });

  updateHeader(read());

  window.addEventListener("storage", (event) => {
    if (event.key !== STORAGE_KEY) {
      return;
    }

    const items = read();
    updateHeader(items);
    window.dispatchEvent(new CustomEvent("cart:updated", { detail: { items } }));
  });
})();
