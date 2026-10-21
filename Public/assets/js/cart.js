// Gère uniquement l’affichage et les interactions de la page Panier.
(() => {
  const page = document.querySelector("[data-cart-page]");

  if (!page || !window.CartStore) {
    return;
  }

  const results = page.querySelector("[data-cart-results]");
  const content = page.querySelector("[data-cart-content]");
  const loader = page.querySelector("[data-cart-loader]");
  let requestController;
  let clearPending = false;
  const pendingItems = new WeakSet();
  let cartRefresh = Promise.resolve();
  const quantityStates = new Map();
  let quantityPump;

  // Aperçu visuel uniquement : CartStore conserve toujours les valeurs confirmées.
  const showQuantityPreview = () => {
    const stored = window.CartStore.items();
    let count = stored.reduce((sum, item) => sum + Number(item.quantity), 0);
    quantityStates.forEach((state, id) => {
      const actual = stored.find(item => Number(item.variantId || item.id) === id);
      count += state.desired - Number(actual?.quantity || 0);
      const input = state.item.querySelector("[data-cart-quantity-input]");
      input.value = String(state.desired);
      state.item.querySelectorAll("[data-cart-quantity-change]").forEach(button => {
        button.disabled = input.disabled || (Number(button.dataset.cartQuantityChange) < 0 ? state.desired <= 1 : state.desired >= Math.min(99, Number(input.max) || 99));
      });
    });
    if (quantityStates.size) {
      document.querySelectorAll("[data-cart-count]").forEach(badge => { badge.textContent = String(count); });
      document.querySelectorAll("[data-cart-link]").forEach(link => link.setAttribute("aria-label", `Panier, ${count} produit${count > 1 ? "s" : ""}`));
      const heading = content.querySelector(".cart-list__header p");
      if (heading) heading.innerHTML = `<strong>${count}</strong> article${count > 1 ? "s" : ""} dans votre panier`;
    }
  };

  // Une quantité ne recrée ni la ligne ni son image, et ne rejoue pas Motion.
  const updateExistingLines = (html) => {
    const next = document.createElement("div");
    next.innerHTML = html;
    const currentLines = [...content.querySelectorAll("[data-cart-item]")];
    const nextLines = [...next.querySelectorAll("[data-cart-item]")];
    if (!currentLines.length || currentLines.length !== nextLines.length ||
        currentLines.some((line, index) => line.dataset.productVariantId !== nextLines[index].dataset.productVariantId)) return false;

    currentLines.forEach((line, index) => {
      const fresh = nextLines[index];
      if (line.classList.contains("is-unavailable") !== fresh.classList.contains("is-unavailable")) {
        line.classList.toggle("is-unavailable", fresh.classList.contains("is-unavailable"));
        line.querySelector(".cart-item__actions").innerHTML = fresh.querySelector(".cart-item__actions").innerHTML;
      }
      const input = line.querySelector("[data-cart-quantity-input]");
      const quantity = fresh.querySelector("[data-cart-quantity-input]");
      input.value = quantity.value;
      input.defaultValue = quantity.value;
      input.max = quantity.max;
      input.disabled = quantity.disabled;
      const state = quantityStates.get(Number(line.dataset.productVariantId));
      if (state) {
        state.confirmed = Number(quantity.value);
        if (state.revision === state.sentRevision || input.disabled) state.desired = state.confirmed;
        else state.desired = Math.min(state.desired, 99, Number(quantity.max) || 99);
      }
      const controls = line.querySelectorAll("[data-cart-quantity-change]");
      fresh.querySelectorAll("[data-cart-quantity-change]").forEach((button, i) => { controls[i].disabled = button.disabled; });
      for (const selector of [".cart-item__title", ".cart-item__prices", ".cart-item__availability"]) {
        const current = line.querySelector(selector), replacement = fresh.querySelector(selector);
        // Les SVG Lucide existants ne sont pas remplacés lorsque le texte est identique.
        if (current.textContent.trim() !== replacement.textContent.trim() || selector !== ".cart-item__availability" && current.innerHTML !== replacement.innerHTML) current.innerHTML = replacement.innerHTML;
      }
      const image = line.querySelector("img"), newImage = fresh.querySelector("img");
      for (const attribute of ["src", "alt"]) {
        if (image.getAttribute(attribute) !== newImage.getAttribute(attribute)) image.setAttribute(attribute, newImage.getAttribute(attribute));
      }
    });
    for (const selector of [".cart-list__header p", ".cart-summary"]) {
      const current = content.querySelector(selector), replacement = next.querySelector(selector);
      if (current.innerHTML !== replacement.innerHTML) current.innerHTML = replacement.innerHTML;
    }
    return true;
  };

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
    loader.hidden = content.childElementCount > 0;

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
      if (controller.signal.aborted || requestController !== controller) return;
      const updatedInPlace = updateExistingLines(payload.html);
      if (!updatedInPlace) content.innerHTML = payload.html;

      if (JSON.stringify(payload.items) !== JSON.stringify(requestedItems)) {
        window.CartStore.replace(payload.items, false);
      }

      if (payload.notice) {
        notify(payload.notice, "warning");
      }

      window.lucide?.createIcons();
      if (!updatedInPlace) window.MotionSystem?.refresh(content);
      showQuantityPreview();
    } catch (error) {
      if (error.name !== "AbortError") {
        const dialog = window.MotionSystem?.fire({
          icon: "error",
          title: "Panier indisponible",
          text: "Impossible de charger votre panier pour le moment.",
          confirmButtonText: "Réessayer",
        });

        dialog?.then((result) => {
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

  // Empêche deux mutations simultanées sur la même ligne du panier.
  const beginItemAction = (item) => {
    if (!item || pendingItems.has(item) || quantityStates.size) {
      return false;
    }

    pendingItems.add(item);
    item.setAttribute("aria-busy", "true");

    return true;
  };

  // Rend la ligne de nouveau disponible après la réponse du stockage.
  const finishItemAction = (item) => {
    pendingItems.delete(item);
    item?.removeAttribute("aria-busy");
  };

  // Une mutation à la fois ; les clics intermédiaires deviennent une cible finale.
  const drainQuantities = async () => {
    while (quantityStates.size) {
      const [id, state] = quantityStates.entries().next().value;
      const sent = state.desired;
      state.sentRevision = state.revision;
      try {
        await window.CartStore.setQuantity(id, sent);
        const accepted = window.CartStore.items().find(item => Number(item.variantId || item.id) === id);
        state.confirmed = Number(accepted?.quantity || 1);
        if (state.revision === state.sentRevision) state.desired = state.confirmed;
        if (state.confirmed !== sent) notify("Quantité ajustée selon le stock disponible.", "warning");
        let settledRefresh;
        do { settledRefresh = cartRefresh; await settledRefresh; } while (settledRefresh !== cartRefresh);
      } catch (error) {
        state.desired = state.confirmed;
        state.sentRevision = state.revision;
        notify(error?.message || "Impossible de modifier cette quantité.", "error");
        // Une erreur réseau peut suivre une écriture : relire avant un nouvel essai.
        if (window.CartStore.refresh) {
          try { await window.CartStore.refresh(); cartRefresh = loadCart(); await cartRefresh; }
          catch { /* Le dernier état confirmé reste affiché avec le message d'erreur. */ }
        }
      }
      showQuantityPreview();
      if (state.desired === state.confirmed) {
        state.item.querySelector("[data-cart-quantity-input]").defaultValue = String(state.confirmed);
        quantityStates.delete(id);
        state.item.removeAttribute("aria-busy");
      }
    }
  };

  const updateQuantity = (item, quantity) => {
    const input = item?.querySelector("[data-cart-quantity-input]");

    if (!input || input.disabled || pendingItems.has(item) || clearPending) {
      return;
    }

    const minimum = Number(input.min) || 1;
    const maximum = Math.min(99, Number(input.max) || 99);
    const requested = Number(quantity) || minimum;
    const normalized = Math.min(maximum, Math.max(minimum, Math.trunc(requested)));

    if (normalized !== requested) {
      notify("Quantité ajustée selon le stock disponible.", "warning");
    }

    const id = Number(item.dataset.productVariantId || item.dataset.productId);
    const state = quantityStates.get(id) || {item, desired: Number(input.defaultValue), confirmed: Number(input.defaultValue), revision: 0, sentRevision: 0};
    state.desired = normalized; state.revision++;
    quantityStates.set(id, state);
    item.setAttribute("aria-busy", "true");
    showQuantityPreview();
    if (!quantityPump) quantityPump = Promise.resolve().then(drainQuantities).finally(() => { quantityPump = null; });
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
    if (quantityStates.size && event.target.closest(".cart-summary__checkout")) {
      event.preventDefault();
      notify("Mise à jour du panier en cours. Un instant…", "info");
      return;
    }
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

    const removeButton = event.target.closest("[data-cart-remove]");

    if (item && removeButton) {
      if (!beginItemAction(item)) {
        return;
      }

      removeButton.disabled = true;
      removeButton.setAttribute("aria-busy", "true");
      let removed = false;

      try {
        await window.CartStore.remove(
          Number(item.dataset.productVariantId || item.dataset.productId),
        );
        removed = true;
        notify("Produit retiré du panier.");
      } catch (error) {
        notify(error?.message || "Impossible de retirer ce produit.", "error");
      } finally {
        finishItemAction(item);

        if (!removed) {
          removeButton.disabled = false;
          removeButton.removeAttribute("aria-busy");
        }
      }
      return;
    }

    if (item && event.target.closest("[data-cart-wait]")) {
      notify("Ce produit reste dans votre panier.", "info");
      return;
    }

    const clearButton = event.target.closest("[data-cart-clear]");

    if (clearButton) {
      if (clearPending || quantityStates.size) {
        return;
      }

      clearPending = true;
      clearButton.disabled = true;
      let cleared = false;

      try {
        const result = await window.MotionSystem?.fire({
          icon: "question",
          title: "Vider le panier ?",
          text: "Tous les produits seront retirés de votre panier.",
          showCancelButton: true,
          confirmButtonText: "Vider",
          cancelButtonText: "Annuler",
        });

        if (result?.isConfirmed) {
          await window.CartStore.clear();
          cleared = true;
        }
      } catch (error) {
        notify(error?.message || "Impossible de vider le panier.", "error");
      } finally {
        clearPending = false;

        if (!cleared) {
          clearButton.disabled = false;
        }
      }
      return;
    }

  });

  const refresh = () => { cartRefresh = loadCart(); showQuantityPreview(); };
  window.addEventListener("cart:updated", refresh);
  window.addEventListener("cart:server-updated", refresh);
  refresh();
})();
