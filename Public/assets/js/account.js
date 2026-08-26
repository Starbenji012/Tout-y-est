(() => {
  const view = document.querySelector("[data-auth-view]");

  if (!view) {
    return;
  }

  let switching = false;
  const status = view.querySelector("[data-auth-status]");
  const validation = window.ValidationSystem?.create(view);

  const passwordStrength = (password) => {
    let score = 0;

    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;

    return Math.min(4, score);
  };

  const updateStrength = (input) => {
    const indicator = view.querySelector("[data-password-strength]");

    if (!indicator) {
      return;
    }

    const score = passwordStrength(input.value);
    const labels = ["Mot de passe à compléter", "Faible", "Correct", "Bon", "Robuste"];
    indicator.dataset.strength = String(score);
    indicator.querySelector("small").textContent = labels[score];
  };

  const togglePassword = (toggle) => {
    const input = toggle.closest(".account-password")?.querySelector("[data-password-input]");

    if (!input) {
      return;
    }

    const visible = input.type === "password";
    input.type = visible ? "text" : "password";
    toggle.setAttribute("aria-pressed", String(visible));
    toggle.setAttribute("aria-label", visible ? "Masquer le mot de passe" : "Afficher le mot de passe");
    const icon = document.createElement("i");
    icon.dataset.lucide = visible ? "eye-off" : "eye";
    icon.setAttribute("aria-hidden", "true");
    toggle.replaceChildren(icon);
    window.lucide?.createIcons();
  };

  const switchPanel = (mode) => {
    if (switching || !["login", "register"].includes(mode)) {
      return;
    }

    const currentPanel = view.querySelector("[data-auth-panel]:not([hidden])");
    const nextPanel = view.querySelector(`[data-auth-panel='${mode}']`);

    if (!currentPanel || !nextPanel || currentPanel === nextPanel) {
      return;
    }

    switching = true;
    view.dataset.activeMode = mode;
    window.history.replaceState({}, "", mode === "register" ? "#inscription" : window.location.pathname);

    const completeSwitch = () => {
      switching = false;
      if (status) {
        status.textContent = mode === "register"
          ? "Formulaire de création de compte affiché."
          : "Formulaire de connexion affiché.";
      }
      nextPanel.querySelector("input:not([type='hidden'])")?.focus();
    };

    if (window.MotionSystem?.swapPanels) {
      window.MotionSystem.swapPanels(currentPanel, nextPanel, completeSwitch);
      return;
    }

    currentPanel.hidden = true;
    nextPanel.hidden = false;
    completeSwitch();
  };

  const setLoading = (form) => {
    const button = form.querySelector("[data-auth-submit]");

    if (!button) {
      return;
    }

    button.disabled = true;
    button.classList.add("is-loading");
    form.setAttribute("aria-busy", "true");
    button.textContent = button.dataset.loadingLabel || "Chargement…";
  };

  const initializeRetry = () => {
    const retryMessage = view.querySelector("[data-auth-retry]");
    const submitButton = view.querySelector("[data-auth-panel='login'] [data-auth-submit]");
    let remaining = Number(retryMessage?.dataset.authRetry) || 0;

    if (!retryMessage || !submitButton || remaining < 1) {
      return;
    }

    const originalContent = submitButton.innerHTML;
    submitButton.disabled = true;

    const update = () => {
      retryMessage.textContent = remaining > 0
        ? `Nouvelle tentative disponible dans ${remaining} seconde${remaining > 1 ? "s" : ""}.`
        : "Vous pouvez maintenant réessayer.";

      if (remaining < 1) {
        submitButton.disabled = false;
        submitButton.innerHTML = originalContent;
        window.lucide?.createIcons();
        return;
      }

      submitButton.textContent = `Réessayer dans ${remaining} s`;
      remaining -= 1;
      window.setTimeout(update, 1000);
    };

    update();
  };

  view.addEventListener("focusout", (event) => {
    if (event.target.matches("[data-validate]")) {
      event.target.dataset.touched = "true";
      validation?.validateField(event.target, true);
    }
  });

  view.addEventListener("input", (event) => {
    if (!event.target.matches("[data-validate]")) {
      return;
    }

    validation?.validateField(event.target);

    if (event.target.matches("[data-register-password]")) {
      updateStrength(event.target);
      const confirmation = view.querySelector("[data-password-confirmation]");
      if (confirmation?.dataset.touched === "true") validation?.validateField(confirmation, true);
    }
  });

  view.addEventListener("click", (event) => {
    const switchButton = event.target.closest("[data-auth-switch]");
    if (switchButton) {
      switchPanel(switchButton.dataset.authSwitch);
      return;
    }

    const passwordToggle = event.target.closest("[data-password-toggle]");
    if (passwordToggle) {
      togglePassword(passwordToggle);
      return;
    }

    if (event.target.closest("[data-forgot-password]")) {
      const recoveryMessage = "Contactez le support Tout y est afin de vérifier votre identité et récupérer l’accès à votre compte.";

      if (!window.Swal || !window.MotionSystem?.fire) {
        window.alert(recoveryMessage);
        return;
      }

      window.MotionSystem.fire({
        icon: "info",
        title: "Récupération sécurisée",
        text: recoveryMessage,
        confirmButtonText: "Compris",
      });
      return;
    }

    if (event.target.closest("[data-google-auth]")) {
      const googleMessage = "La connexion Google nécessite encore les identifiants OAuth et un stockage sécurisé de l’identifiant fournisseur.";

      if (!window.Swal || !window.MotionSystem?.fire) {
        window.alert(googleMessage);
        return;
      }

      window.MotionSystem.fire({
        icon: "info",
        title: "Connexion Google à configurer",
        text: googleMessage,
        confirmButtonText: "Compris",
      });
    }
  });

  view.addEventListener("submit", (event) => {
    const form = event.target.closest("[data-auth-form]");

    if (!form) {
      return;
    }

    const result = validation?.validateForm(form) || { valid: true, fields: [] };

    if (!result.valid) {
      event.preventDefault();
      result.fields.find((input) => !input.checkValidity())?.focus();
      return;
    }

    setLoading(form);
  });

  if (window.location.hash === "#inscription" && view.dataset.activeMode !== "register") {
    switchPanel("register");
  }

  initializeRetry();
})();
