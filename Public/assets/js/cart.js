(() => {
  const page = document.querySelector("[data-cart-page]");

  if (!page || !window.CartStore) {
    return;
  }

  const results = page.querySelector("[data-cart-results]");
  const content = page.querySelector("[data-cart-content]");
  const loader = page.querySelector("[data-cart-loader]");
  let requestController;

  const loadCart = async () => {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const requestedItems = window.CartStore.items();

    results.setAttribute("aria-busy", "true");
    loader.hidden = false;

    try {
      const response = await fetch(`/api/panier?items=${encodeURIComponent(window.CartStore.serialize())}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error("Cart unavailable");
      }

      const payload = await response.json();
      content.innerHTML = payload.html;

      if (JSON.stringify(payload.items) !== JSON.stringify(requestedItems)) {
        window.CartStore.replace(payload.items);
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

  const updateQuantity = (item, quantity) => {
    const input = item.querySelector("[data-cart-quantity-input]");
    const minimum = Number(input.min) || 1;
    const maximum = Number(input.max) || 99;
    const normalized = Math.min(maximum, Math.max(minimum, Number(quantity) || minimum));
    window.CartStore.setQuantity(Number(item.dataset.productId), normalized);
  };

  page.addEventListener("change", (event) => {
    if (event.target.matches("[data-cart-quantity-input]")) {
      updateQuantity(event.target.closest("[data-cart-item]"), event.target.value);
    }
  });

  page.addEventListener("click", async (event) => {
    const item = event.target.closest("[data-cart-item]");
    const quantityButton = event.target.closest("[data-cart-quantity-change]");

    if (item && quantityButton) {
      const input = item.querySelector("[data-cart-quantity-input]");
      updateQuantity(item, Number(input.value) + Number(quantityButton.dataset.cartQuantityChange));
      return;
    }

    if (item && event.target.closest("[data-cart-remove]")) {
      window.CartStore.remove(Number(item.dataset.productId));
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
      window.MotionSystem?.fire({
        icon: "info",
        title: "Panier prêt",
        text: "Le tunnel de commande sécurisé sera connecté lors de la prochaine étape.",
        confirmButtonText: "Continuer mes achats",
      });
    }
  });

  window.addEventListener("cart:updated", loadCart);
  loadCart();
})();
