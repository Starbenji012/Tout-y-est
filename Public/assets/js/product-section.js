(() => {
  document.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-product-action]");

    if (!actionButton) {
      return;
    }

    const productCard = actionButton.closest("[data-product-card]");
    const productSection = actionButton.closest("[data-product-section]");

    if (!productCard || !productSection) {
      return;
    }

    productSection.dispatchEvent(
      new CustomEvent("product:action", {
        bubbles: true,
        detail: {
          action: actionButton.dataset.productAction,
          productId: productCard.dataset.productId,
        },
      }),
    );
  });
})();
