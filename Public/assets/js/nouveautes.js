(() => {
  const section = document.querySelector("[data-new-products]");

  if (!section) {
    return;
  }

  section.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-product-action]");

    if (!actionButton || !section.contains(actionButton)) {
      return;
    }

    const productCard = actionButton.closest("[data-product-card]");

    section.dispatchEvent(
      new CustomEvent("product:action", {
        bubbles: true,
        detail: {
          action: actionButton.dataset.productAction,
          productId: productCard?.dataset.productId ?? null,
        },
      }),
    );
  });
})();
