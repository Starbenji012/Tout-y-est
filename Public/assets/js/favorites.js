(() => {
  const page = document.querySelector("[data-favorites-page]");

  if (!page || !window.FavoriteStore) {
    return;
  }

  const results = page.querySelector("[data-favorites-results]");
  const content = page.querySelector("[data-favorites-content]");
  const loader = page.querySelector("[data-favorites-loader]");
  let requestController;

  const refreshEnhancements = () => {
    window.lucide?.createIcons();
    window.MotionSystem?.refresh(content);
    window.dispatchEvent(new Event("favorites:sync"));
  };

  const showError = () => {
    window.MotionSystem?.fire({
      icon: "error",
      title: "Favoris indisponibles",
      text: "Impossible de charger votre sélection pour le moment.",
      confirmButtonText: "Réessayer",
    }).then((result) => {
      if (result.isConfirmed) {
        loadFavorites();
      }
    });
  };

  const loadFavorites = async () => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const requestedIds = window.FavoriteStore.ids();

    results.setAttribute("aria-busy", "true");
    loader.hidden = false;

    try {
      const response = await fetch(`/api/favoris?ids=${encodeURIComponent(requestedIds.join(","))}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error("Favorites unavailable");
      }

      const payload = await response.json();
      content.innerHTML = payload.html;

      if (JSON.stringify(payload.ids) !== JSON.stringify(requestedIds)) {
        window.FavoriteStore.replace(payload.ids);
      }

      refreshEnhancements();
    } catch (error) {
      if (error.name !== "AbortError") {
        showError();
      }
    } finally {
      if (requestController === controller) {
        loader.hidden = true;
        results.setAttribute("aria-busy", "false");
      }
    }
  };

  window.addEventListener("favorites:updated", loadFavorites);
  loadFavorites();
})();
