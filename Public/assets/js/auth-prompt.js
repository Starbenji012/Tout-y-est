(() => {
  const isAuthenticated = document.body.dataset.authenticated === "true";
  const invitationKey = "tout-y-est:favorite-login-invitation";

  if (isAuthenticated) {
    return;
  }

  const showInvitation = (options) => {
    if (!window.Swal || !window.MotionSystem?.fire) {
      options.onUnavailable?.();
      return;
    }

    window.MotionSystem.fire({
      icon: "info",
      title: "Retrouvez vos favoris partout",
      text: options.text,
      confirmButtonText: "Se connecter",
      cancelButtonText: options.cancelLabel,
      showCancelButton: true,
      focusCancel: true,
    })?.then((result) => {
      if (result.isConfirmed) {
        window.location.assign("/connexion?return=/favoris");
      } else {
        options.onContinue?.();
      }
    });
  };

  const favoriteSaved = () => {
    try {
      if (window.sessionStorage.getItem(invitationKey) === "shown") {
        return;
      }
      window.sessionStorage.setItem(invitationKey, "shown");
    } catch {
      return;
    }

    showInvitation({
      text: "Ce produit reste enregistré sur cet appareil. Connectez-vous pour retrouver plus tard vos favoris et préparer leur synchronisation.",
      cancelLabel: "Continuer mes achats",
    });
  };

  document.addEventListener("click", (event) => {
    const favoritesLink = event.target.closest("[data-favorites-link]");

    if (!favoritesLink) {
      return;
    }

    event.preventDefault();
    showInvitation({
      text: "Vos favoris locaux restent disponibles sans compte. Connectez-vous pour préparer leur synchronisation avec votre espace.",
      cancelLabel: "Voir sur cet appareil",
      onContinue: () => window.location.assign("/favoris"),
      onUnavailable: () => window.location.assign("/favoris"),
    });
  });

  window.AuthPrompt = Object.freeze({ favoriteSaved });
})();
