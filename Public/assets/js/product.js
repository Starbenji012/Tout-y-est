(() => {
  const product = document.querySelector("[data-product-detail]");
  const zoomButton = product?.querySelector("[data-product-zoom]");
  const imageContainer = product?.querySelector(".product-detail__image-container");

  if (!product || !zoomButton || !imageContainer) {
    return;
  }

  zoomButton.addEventListener("click", () => {
    const zoomed = !imageContainer.classList.contains("is-zoomed");
    imageContainer.classList.toggle("is-zoomed", zoomed);
    zoomButton.setAttribute("aria-pressed", String(zoomed));
    zoomButton.setAttribute("aria-label", zoomed ? "Réduire l’image" : "Agrandir l’image");
  });
})();
