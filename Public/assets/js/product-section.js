(() => {
  const quickViewHost = document.querySelector("[data-quick-view-host]");
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const notify = (title, icon = "success") => {
    window.Swal?.fire({ toast: true, position: "bottom-end", icon, title, showConfirmButton: false, timer: 2200, timerProgressBar: true });
  };

  const normalizeQuantity = (input, value) => {
    const minimum = Number(input.min) || 1;
    const maximum = Number(input.max) || Number.MAX_SAFE_INTEGER;
    return Math.min(maximum, Math.max(minimum, Number(value) || minimum));
  };

  const changeGalleryImage = (thumbnail) => {
    const gallery = thumbnail.closest(".quick-view__gallery, .product-detail__media");
    const mainImage = gallery?.querySelector("[data-gallery-main]");
    if (!mainImage) {
      return;
    }

    mainImage.src = thumbnail.dataset.imageSrc;
    mainImage.alt = thumbnail.dataset.imageAlt;
    gallery.querySelectorAll("[data-gallery-thumbnail]").forEach((button) => {
      const active = button === thumbnail;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", String(active));
    });
  };

  const toggleFavorite = (button) => {
    const active = button.getAttribute("aria-pressed") !== "true";
    button.setAttribute("aria-pressed", String(active));
    button.classList.toggle("is-active", active);
    notify(active ? "Produit ajouté aux favoris" : "Produit retiré des favoris", active ? "success" : "info");
  };

  const confirmCart = (button, productCard) => {
    const quantity = Number(productCard.querySelector("[data-quantity-input]")?.value) || 1;
    button.classList.remove("is-feedback");
    window.requestAnimationFrame(() => button.classList.add("is-feedback"));
    window.setTimeout(() => button.classList.remove("is-feedback"), 500);
    notify(`${quantity} × ${button.dataset.productName || "Produit"} ajouté au panier`);
  };

  const closeQuickView = (dialog) => {
    if (!dialog?.open || dialog.dataset.closing === "true") {
      return;
    }

    dialog.dataset.closing = "true";
    dialog.classList.add("is-closing");
    const finish = () => dialog.close();

    if (!reducedMotion && typeof window.gsap === "object") {
      window.gsap.to(dialog, { autoAlpha: 0, y: 10, scale: 0.985, duration: 0.2, ease: "power2.in", onComplete: finish });
      return;
    }

    finish();
  };

  const revealQuickView = (dialog) => {
    dialog.showModal();
    dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      closeQuickView(dialog);
    });
    dialog.addEventListener("close", () => {
      if (quickViewHost) {
        quickViewHost.innerHTML = "";
      }
    }, { once: true });

    if (!reducedMotion && typeof window.gsap === "object") {
      window.gsap.fromTo(dialog, { autoAlpha: 0, y: 14, scale: 0.975 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.36, ease: "power3.out", clearProps: "opacity,transform,visibility" });
    }
  };

  const openQuickView = async (productId, button) => {
    if (!quickViewHost || button.disabled) {
      return;
    }

    button.disabled = true;
    try {
      const response = await fetch(`/api/produit/apercu?id=${encodeURIComponent(productId)}`, { headers: { Accept: "application/json" } });
      if (!response.ok) {
        throw new Error("Aperçu indisponible");
      }
      const result = await response.json();
      quickViewHost.innerHTML = result.html;
      window.lucide?.createIcons();
      const dialog = quickViewHost.querySelector("[data-quick-view-dialog]");
      if (dialog) {
        revealQuickView(dialog);
      }
    } catch {
      notify("Impossible d’afficher ce produit", "error");
    } finally {
      button.disabled = false;
    }
  };

  document.addEventListener("change", (event) => {
    if (event.target.matches("[data-quantity-input]")) {
      event.target.value = String(normalizeQuantity(event.target, event.target.value));
    }
  });

  document.addEventListener("click", (event) => {
    const closeButton = event.target.closest("[data-quick-view-close]");
    if (closeButton) {
      closeQuickView(closeButton.closest("dialog"));
      return;
    }

    const dialog = event.target.closest("[data-quick-view-dialog]");
    if (dialog && event.target === dialog) {
      closeQuickView(dialog);
      return;
    }

    const thumbnail = event.target.closest("[data-gallery-thumbnail]");
    if (thumbnail) {
      changeGalleryImage(thumbnail);
      return;
    }

    const quantityButton = event.target.closest("[data-quantity-change]");
    if (quantityButton) {
      const input = quantityButton.closest(".quantity-control")?.querySelector("[data-quantity-input]");
      if (input) {
        input.value = String(normalizeQuantity(input, Number(input.value) + Number(quantityButton.dataset.quantityChange)));
      }
      return;
    }

    const actionButton = event.target.closest("[data-product-action]");
    const productCard = actionButton?.closest("[data-product-card]");
    if (!actionButton || !productCard) {
      return;
    }

    const action = actionButton.dataset.productAction;
    if (action === "favorite") {
      toggleFavorite(actionButton);
    } else if (action === "cart") {
      confirmCart(actionButton, productCard);
    } else if (action === "quick-view") {
      openQuickView(productCard.dataset.productId, actionButton);
    }

    productCard.closest("[data-product-section]")?.dispatchEvent(new CustomEvent("product:action", { bubbles: true, detail: { action, productId: productCard.dataset.productId } }));
  });
})();
