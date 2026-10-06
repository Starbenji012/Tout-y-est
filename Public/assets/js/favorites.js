// Charge et maintient la page des favoris à partir du stockage partagé.
(() => {
  const page = document.querySelector("[data-favorites-page]");

  if (!page || !window.FavoriteStore) {
    return;
  }

  const results = page.querySelector("[data-favorites-results]");
  const content = page.querySelector("[data-favorites-content]");
  const loader = page.querySelector("[data-favorites-loader]");
  const clearButton = page.querySelector("[data-favorites-clear]");
  let requestController;
  let clearPending = false;
  const pendingRemovals = new Set();

  // Affiche un retour bref sans interrompre la consultation des favoris.
  const notify = (title, icon = "success") =>
    window.MotionSystem?.fire({
      toast: true,
      position: "bottom-end",
      icon,
      title,
      showConfirmButton: false,
      timer: 2400,
      timerProgressBar: true,
    });

  // Réactive icônes, animations et interactions après un rendu dynamique.
  const refreshEnhancements = () => {
    window.lucide?.createIcons();
    window.MotionSystem?.refresh(content);
    window.dispatchEvent(new Event("favorites:sync"));
  };

  // Présente une erreur discrète lorsque la sélection ne peut pas être chargée.
  const showError = () => {
    const dialog = window.MotionSystem?.fire({
      icon: "error",
      title: "Favoris indisponibles",
      text: "Impossible de charger votre sélection pour le moment.",
      confirmButtonText: "Réessayer",
    });

    dialog?.then((result) => {
      if (result.isConfirmed) {
        loadFavorites();
      }
    });
  };

  // Demande au serveur les cartes correspondant aux favoris enregistrés.
  const loadFavorites = async () => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    results.setAttribute("aria-busy", "true");
    loader.hidden = false;

    try {
      await window.FavoriteStore.ready();
      const requestedIds = window.FavoriteStore.ids();
      const response = await fetch(`/api/favoris?ids=${encodeURIComponent(requestedIds.join(","))}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error("Favorites unavailable");
      }

      const payload = await response.json();
      content.innerHTML = payload.html;
      clearButton.hidden = payload.ids.length === 0;

      if (JSON.stringify(payload.ids) !== JSON.stringify(requestedIds)) {
        window.FavoriteStore.replace(payload.ids, false);
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

  // Retire un favori une seule fois et attend sa persistance avant confirmation.
  page.addEventListener("click", async (event) => {
    if (event.target.closest("[data-favorites-clear]")) {
      if (clearPending || window.FavoriteStore.ids().length === 0) {
        return;
      }

      clearPending = true;
      clearButton.disabled = true;

      try {
        const result = await window.MotionSystem?.fire({
          icon: "question",
          title: "Vider les favoris ?",
          text: "Tous les produits seront retirés de votre sélection.",
          showCancelButton: true,
          confirmButtonText: "Vider",
          cancelButtonText: "Annuler",
        });

        if (result?.isConfirmed) {
          await window.FavoriteStore.clear();
          clearButton.hidden = true;
          notify("Favoris vidés.");
        }
      } catch (error) {
        notify(error?.message || "Impossible de vider les favoris.", "error");
      } finally {
        clearPending = false;
        clearButton.disabled = false;
      }

      return;
    }

    const button = event.target.closest("[data-favorite-remove]");
    const productCard = button?.closest("[data-product-card]");
    const productId = Number(productCard?.dataset.productId);

    if (
      !button ||
      !Number.isInteger(productId) ||
      productId < 1 ||
      pendingRemovals.has(productId)
    ) {
      return;
    }

    pendingRemovals.add(productId);
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    let removed = false;

    try {
      const active = await window.FavoriteStore.toggle(productId);

      if (active) {
        throw new Error("Ce favori n’a pas pu être supprimé.");
      }

      removed = true;
      notify("Produit retiré des favoris.");
    } catch (error) {
      notify(error?.message || "Impossible de supprimer ce favori.", "error");
    } finally {
      pendingRemovals.delete(productId);

      if (!removed) {
        button.disabled = false;
        button.removeAttribute("aria-busy");
      }
    }
  });

  window.addEventListener("favorites:updated", loadFavorites);
  loadFavorites();
})();
