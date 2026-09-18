// Centralise le panier local afin que toutes les pages partagent les mêmes données.
(() => {
  const STORAGE_KEY = "tout-y-est:cart";
  const isAuthenticated = document.body.dataset.authenticated === "true";
  let serverItems = [];

  if (document.body.dataset.cartFusionCompleted === "true") {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Le panier serveur reste disponible même si le stockage local est inaccessible.
    }
  }

  // Nettoie les identifiants et quantités avant toute sauvegarde.
  const normalize = (values) => {
    const items = new Map();

    (Array.isArray(values) ? values : []).slice(0, 40).forEach((item) => {
      const id = Number(item?.variantId ?? item?.id);
      const quantity = Math.min(99, Math.max(1, Number(item?.quantity) || 1));

      if (Number.isInteger(id) && id > 0) {
        items.set(id, { id, variantId: id, quantity });
      }
    });

    return [...items.values()];
  };

  // Lit le panier local sans laisser une donnée invalide casser l'interface.
  const read = () => {
    if (isAuthenticated) {
      return serverItems;
    }

    try {
      return normalize(
        JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]"),
      );
    } catch {
      return [];
    }
  };

  // Synchronise le badge et le libellé du panier dans le Header.
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

  // Signale que le compteur serveur n'a pas pu être chargé.
  const markHeaderUnavailable = () => {
    document.querySelectorAll("[data-cart-count]").forEach((badge) => {
      badge.textContent = "–";
    });
    document.querySelectorAll("[data-cart-link]").forEach((link) => {
      link.setAttribute("aria-label", "Panier temporairement indisponible");
    });
  };

  // Enregistre le panier normalisé puis informe les composants concernés.
  const write = (values, notify = true) => {
    const items = normalize(values);

    if (isAuthenticated) {
      serverItems = items;
      updateHeader(items);
      return items;
    }

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      return read();
    }

    updateHeader(items);
    if (notify) {
      window.dispatchEvent(
        new CustomEvent("cart:updated", { detail: { items } }),
      );
    }
    return items;
  };

  // Décode uniquement les réponses JSON attendues de l'API panier.
  const readJson = async (response) => {
    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      if (response.redirected && response.url.includes("/connexion")) {
        throw new Error("Votre session a expiré. Reconnectez-vous puis réessayez.");
      }

      throw new Error(
        response.status >= 500
          ? "Le serveur ne peut pas modifier le panier pour le moment."
          : "La réponse du panier est invalide.",
      );
    }

    let payload;

    try {
      payload = await response.json();
    } catch {
      throw new Error("La réponse du panier est illisible.");
    }

    if (!response.ok) {
      const messages = {
        419: "Votre session a expiré. Actualisez la page puis réessayez.",
        422: "Cette variante n'est plus disponible.",
      };
      throw new Error(
        payload.error ||
          messages[response.status] ||
          "Impossible de modifier le panier.",
      );
    }

    return payload;
  };

  // Charge l'état persistant afin d'initialiser le compteur connecté.
  const loadServerCart = async () => {
    const response = await fetch("/api/panier", {
      headers: { Accept: "application/json" },
    });
    const payload = await readJson(response);
    write(payload.items || [], false);
    return payload;
  };

  // Persiste une mutation uniquement pour un utilisateur connecté.
  const mutate = async (action, variantId = 0, quantity = 1) => {
    try {
      const body = new URLSearchParams({
        _token:
          document.querySelector("meta[name='csrf-token']")?.content || "",
        action,
        variantId: String(variantId),
        quantity: String(quantity),
      });
      const response = await fetch("/api/panier/mutation", {
        method: "POST",
        headers: { Accept: "application/json" },
        body,
      });
      const payload = await readJson(response);

      write(payload.items || [], false);
      window.dispatchEvent(
        new CustomEvent("cart:server-updated", { detail: payload }),
      );
      return payload;
    } catch (error) {
      if (error instanceof TypeError) {
        throw new Error("Connexion au serveur impossible. Vérifiez votre réseau.");
      }

      throw error;
    }
  };

  window.CartStore = Object.freeze({
    items: read,
    replace: (values, notify = true) => write(values, notify),
    serialize: () =>
      read()
        .map((item) => `${item.id}:${item.quantity}`)
        .join(","),
    add: (productId, quantity = 1) => {
      const id = Number(productId);

      if (!Number.isInteger(id) || id < 1) {
        return read();
      }

      if (isAuthenticated) {
        return mutate("add", id, quantity);
      }

      const items = read();
      const current = items.find((item) => item.id === id);

      if (current) {
        current.quantity = Math.min(
          99,
          current.quantity + Math.max(1, Number(quantity) || 1),
        );
      } else {
        items.push({ id, quantity });
      }

      return write(items);
    },
    setQuantity: (productId, quantity) =>
      isAuthenticated
        ? mutate("set", Number(productId), quantity)
        : write(
            read().map((item) =>
              item.id === Number(productId) ? { ...item, quantity } : item,
            ),
          ),
    remove: (productId) =>
      isAuthenticated
        ? mutate("remove", Number(productId))
        : write(read().filter((item) => item.id !== Number(productId))),
    clear: () => (isAuthenticated ? mutate("clear") : write([])),
    refresh: () =>
      isAuthenticated
        ? loadServerCart()
        : Promise.resolve({ items: read() }),
  });

  if (isAuthenticated) {
    markHeaderUnavailable();
    loadServerCart().catch(markHeaderUnavailable);
  } else {
    updateHeader(read());
  }

  window.addEventListener("storage", (event) => {
    if (event.key !== STORAGE_KEY) {
      return;
    }

    const items = read();
    updateHeader(items);
    window.dispatchEvent(
      new CustomEvent("cart:updated", { detail: { items } }),
    );
  });
})();
