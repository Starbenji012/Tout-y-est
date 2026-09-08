// Gère uniquement l’affichage et les interactions de la page Panier.
(() => {
  const page = document.querySelector("[data-cart-page]");

  if (!page || !window.CartStore) {
    return;
  }

  const results = page.querySelector("[data-cart-results]");
  const content = page.querySelector("[data-cart-content]");
  const loader = page.querySelector("[data-cart-loader]");
  const overview = page.querySelector("[data-cart-overview]");
  const checkoutGate = page.querySelector("[data-cart-checkout-gate]");
  const isAuthenticated = document.body.dataset.authenticated === "true";
  let requestController;

  // Affiche un retour bref sans interrompre la navigation.
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

  // Recharge les prix et les stocks validés par le serveur.
  const loadCart = async () => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const requestedItems = window.CartStore.items();

    results.setAttribute("aria-busy", "true");
    loader.hidden = false;

    try {
      const response = await fetch(
        `/api/panier?items=${encodeURIComponent(window.CartStore.serialize())}`,
        {
          headers: { Accept: "application/json" },
          signal: controller.signal,
        },
      );

      if (!response.ok) {
        throw new Error("Cart unavailable");
      }

      const payload = await response.json();
      content.innerHTML = payload.html;

      if (JSON.stringify(payload.items) !== JSON.stringify(requestedItems)) {
        window.CartStore.replace(payload.items, false);
      }

      if (payload.items.length === 0 && !checkoutGate.hidden) {
        checkoutGate.hidden = true;
        overview.hidden = false;
      }

      if (payload.notice) {
        notify(payload.notice, "warning");
      }

      window.lucide?.createIcons();
      window.MotionSystem?.refresh(content);
    } catch (error) {
      if (error.name !== "AbortError") {
        window.MotionSystem?.fire({
          icon: "error",
          title: "Panier indisponible",
          text: "Impossible de charger votre panier pour le moment.",
          confirmButtonText: "Réessayer",
        }).then((result) => {
          if (result.isConfirmed) {
            loadCart();
          }
        });
      }
    } finally {
      if (requestController === controller) {
        loader.hidden = true;
        results.setAttribute("aria-busy", "false");
      }
    }
  };

  // Limite la quantité à la plage autorisée avant de la sauvegarder.
  const updateQuantity = (item, quantity) => {
    const input = item?.querySelector("[data-cart-quantity-input]");

    if (!input || input.disabled) {
      return;
    }

    const minimum = Number(input.min) || 1;
    const maximum = Number(input.max) || 99;
    const requested = Number(quantity) || minimum;
    const normalized = Math.min(maximum, Math.max(minimum, requested));

    if (normalized !== requested) {
      notify("Quantité ajustée selon le stock disponible.", "warning");
    }

    window.CartStore.setQuantity(
      Number(item.dataset.productVariantId || item.dataset.productId),
      normalized,
    );
  };

  // Affiche l’étape d’identification sans perdre le panier invité.
  const openCheckoutGate = () => {
    overview.hidden = true;
    checkoutGate.hidden = false;
    checkoutGate
      .querySelector("#cart-checkout-gate-title")
      ?.focus({ preventScroll: true });
    checkoutGate.scrollIntoView({ behavior: "smooth", block: "start" });
    window.lucide?.createIcons();
    window.MotionSystem?.refresh(checkoutGate);
  };

  page.addEventListener("change", (event) => {
    if (event.target.matches("[data-cart-quantity-input]")) {
      updateQuantity(
        event.target.closest("[data-cart-item]"),
        event.target.value,
      );
    }
  });

  page.addEventListener("click", async (event) => {
    const item = event.target.closest("[data-cart-item]");
    const quantityButton = event.target.closest("[data-cart-quantity-change]");

    if (item && quantityButton) {
      const input = item.querySelector("[data-cart-quantity-input]");
      updateQuantity(
        item,
        Number(input.value) + Number(quantityButton.dataset.cartQuantityChange),
      );
      return;
    }

    if (item && event.target.closest("[data-cart-remove]")) {
      window.CartStore.remove(
        Number(item.dataset.productVariantId || item.dataset.productId),
      );
      notify("Produit retiré du panier.");
      return;
    }

    if (item && event.target.closest("[data-cart-wait]")) {
      notify("Ce produit reste dans votre panier.", "info");
      return;
    }

    if (event.target.closest("[data-cart-clear]")) {
      const result = await window.MotionSystem?.fire({
        icon: "question",
        title: "Vider le panier ?",
        text: "Tous les produits seront retirés de votre panier.",
        showCancelButton: true,
        confirmButtonText: "Vider",
        cancelButtonText: "Annuler",
      });

      if (result?.isConfirmed) {
        window.CartStore.clear();
      }
      return;
    }

    if (event.target.closest("[data-cart-checkout]")) {
      if (!isAuthenticated) {
        openCheckoutGate();
        return;
      }

      window.MotionSystem?.fire({
        icon: "info",
        title: "Panier prêt",
        text: "L’étape de livraison sera disponible avec le tunnel de commande.",
        confirmButtonText: "Compris",
      });
      return;
    }

    if (event.target.closest("[data-cart-gate-back]")) {
      checkoutGate.hidden = true;
      overview.hidden = false;
      overview.focus({ preventScroll: true });
      overview.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });

  window.addEventListener("cart:updated", loadCart);
  loadCart();
})();
