// Centralise les favoris invités et connectés sans mélanger leurs sources.
(() => {
  const STORAGE_KEY = "tout-y-est:favorites";
  const isAuthenticated = document.body.dataset.authenticated === "true";
  let serverIds = [];
  let readyPromise = Promise.resolve([]);

  // Conserve uniquement des identifiants positifs, uniques et limités.
  const normalize = (values) => [...new Set(
    (Array.isArray(values) ? values : [])
      .map(Number)
      .filter((value) => Number.isInteger(value) && value > 0),
  )].slice(0, 40);

  // Lit uniquement la sélection invitée conservée sur cet appareil.
  const readLocal = () => {
    try {
      return normalize(JSON.parse(window.localStorage.getItem(STORAGE_KEY) || "[]"));
    } catch {
      return [];
    }
  };

  // Retourne la source adaptée au statut de connexion courant.
  const read = () => isAuthenticated ? serverIds : readLocal();

  // Synchronise le compteur Favoris du Header.
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

  // Signale que l'état personnel n'a pas encore pu être chargé.
  const markHeaderUnavailable = () => {
    document.querySelectorAll("[data-favorites-count]").forEach((badge) => {
      badge.textContent = "–";
    });
    document.querySelectorAll("[data-favorites-link]").forEach((link) => {
      link.setAttribute("aria-label", "Favoris temporairement indisponibles");
    });
  };

  // Informe les cartes et la page Favoris d'un nouvel état fiable.
  const announce = (ids) => {
    window.dispatchEvent(new CustomEvent("favorites:updated", { detail: { ids } }));
  };

  // Applique un état provenant exclusivement de l'API utilisateur.
  const applyServer = (values, notify = true) => {
    serverIds = normalize(values);
    updateHeader(serverIds);

    if (notify) {
      announce(serverIds);
    }

    return serverIds;
  };

  // Enregistre uniquement les favoris d'un visiteur.
  const writeLocal = (values, notify = true) => {
    const ids = normalize(values);

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch {
      return readLocal();
    }

    updateHeader(ids);

    if (notify) {
      announce(ids);
    }

    return ids;
  };

  // Supprime le stockage invité uniquement après une fusion confirmée.
  const clearLocal = () => {
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch {
      // L'état serveur reste la seule source du compte connecté.
    }
  };

  // Décode les réponses JSON et conserve leurs statuts métier.
  const readJson = async (response) => {
    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      throw new Error("La réponse des favoris est invalide.");
    }

    let payload;

    try {
      payload = await response.json();
    } catch {
      throw new Error("La réponse des favoris est illisible.");
    }

    if (!response.ok) {
      const messages = {
        401: "Reconnectez-vous pour modifier vos favoris.",
        419: "Votre session a expiré. Actualisez la page puis réessayez.",
        422: "Ce produit ne peut pas être ajouté aux favoris.",
        503: "Les favoris sont temporairement indisponibles.",
      };
      throw new Error(
        payload.error ||
          messages[response.status] ||
          "Impossible de modifier les favoris.",
      );
    }

    return payload;
  };

  // Charge la sélection personnelle sans lire le stockage invité.
  const loadServerState = async () => {
    const response = await fetch("/api/favoris/state", {
      headers: { Accept: "application/json" },
    });
    const payload = await readJson(response);

    return applyServer(payload.ids || []);
  };

  // Fusionne un ancien état invité puis l'efface après confirmation serveur.
  const mergeLocalState = async (ids) => {
    const response = await fetch("/api/favoris/fusion", {
      method: "POST",
      headers: { Accept: "application/json" },
      body: new URLSearchParams({
        _token: document.querySelector("meta[name='csrf-token']")?.content || "",
        ids: normalize(ids).join(","),
      }),
    });
    const payload = await readJson(response);
    clearLocal();

    return applyServer(payload.ids || []);
  };

  // Persiste une action connectée avant de modifier l'interface.
  const mutate = async (action, productId) => {
    try {
      const response = await fetch("/api/favoris/mutation", {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new URLSearchParams({
          _token: document.querySelector("meta[name='csrf-token']")?.content || "",
          action,
          productId: String(productId),
        }),
      });
      const payload = await readJson(response);
      applyServer(payload.ids || []);

      return Boolean(payload.active);
    } catch (error) {
      if (error instanceof TypeError) {
        throw new Error("Connexion au serveur impossible. Vérifiez votre réseau.");
      }

      throw error;
    }
  };

  window.FavoriteStore = Object.freeze({
    ids: read,
    has: (productId) => read().includes(Number(productId)),
    serialize: () => read().join(","),
    ready: () => readyPromise,
    replace: (values, notify = true) =>
      isAuthenticated
        ? applyServer(values, notify)
        : writeLocal(values, notify),
    toggle: (productId) => {
      const normalizedId = Number(productId);

      if (!Number.isInteger(normalizedId) || normalizedId < 1) {
        return isAuthenticated
          ? Promise.reject(new Error("Produit invalide."))
          : false;
      }

      const active = !read().includes(normalizedId);

      if (isAuthenticated) {
        return mutate(active ? "add" : "remove", normalizedId);
      }

      const ids = readLocal();
      const storedIds = writeLocal(
        active ? [...ids, normalizedId] : ids.filter((id) => id !== normalizedId),
      );

      return storedIds.includes(normalizedId);
    },
  });

  if (isAuthenticated) {
    const fusionCompleted = document.body.dataset.favoriteFusionCompleted === "true";
    const pendingGuestIds = fusionCompleted ? [] : readLocal();

    if (fusionCompleted) {
      clearLocal();
    }

    markHeaderUnavailable();
    readyPromise = (pendingGuestIds.length > 0
      ? mergeLocalState(pendingGuestIds)
      : loadServerState()
    ).catch(() => {
      markHeaderUnavailable();
      return [];
    });
  } else {
    updateHeader(readLocal());
    readyPromise = Promise.resolve(readLocal());
  }

  window.addEventListener("storage", (event) => {
    if (isAuthenticated || event.key !== STORAGE_KEY) {
      return;
    }

    const ids = readLocal();
    updateHeader(ids);
    announce(ids);
  });
})();
