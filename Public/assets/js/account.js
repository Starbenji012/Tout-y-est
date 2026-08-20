(() => {
  const view = document.querySelector("[data-auth-view]");

  if (!view) {
    return;
  }

  let switching = false;
  const status = view.querySelector("[data-auth-status]");

  const fieldMessage = (input) => input.closest(".account-field")?.querySelector("[data-field-error]");

  const validationMessage = (input) => {
    const value = input.value.trim();

    if (input.required && value === "") {
      return "Ce champ est obligatoire.";
    }

    switch (input.dataset.validate) {
      case "required":
        return value === "" ? "Ce champ est obligatoire." : "";
      case "name":
        return value.length >= 2 ? "" : "Saisissez au moins 2 caractères.";
      case "email":
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? "" : "Saisissez une adresse e-mail valide.";
      case "phone":
        return /^[0-9+() .-]{8,30}$/.test(value) ? "" : "Saisissez un numéro de téléphone valide.";
      case "password":
        return value.length >= 8 && /[A-Za-z]/.test(value) && /\d/.test(value)
          ? ""
          : "Utilisez au moins 8 caractères, une lettre et un chiffre.";
      case "confirmation": {
        const password = view.querySelector("[data-register-password]")?.value || "";
        return value !== "" && value === password ? "" : "Les mots de passe ne correspondent pas.";
      }
      default:
        return "";
    }
  };

  const validateField = (input, force = false) => {
    if (!force && input.dataset.touched !== "true") {
      return true;
    }

    const message = validationMessage(input);
    const messageElement = fieldMessage(input);
    input.setCustomValidity(message);
    input.setAttribute("aria-invalid", String(message !== ""));
    input.classList.toggle("is-invalid", message !== "");
    input.classList.toggle("is-valid", message === "" && input.value.trim() !== "");

    if (messageElement) {
      messageElement.textContent = message;
    }

    return message === "";
  };

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
    button.textContent = button.dataset.loadingLabel || "Chargement…";
  };

  view.addEventListener("focusout", (event) => {
    if (event.target.matches("[data-validate]")) {
      event.target.dataset.touched = "true";
      validateField(event.target, true);
    }
  });

  view.addEventListener("input", (event) => {
    if (!event.target.matches("[data-validate]")) {
      return;
    }

    validateField(event.target);

    if (event.target.matches("[data-register-password]")) {
      updateStrength(event.target);
      const confirmation = view.querySelector("[data-password-confirmation]");
      if (confirmation?.dataset.touched === "true") validateField(confirmation, true);
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
      window.MotionSystem?.fire({
        icon: "info",
        title: "Récupération sécurisée",
        text: "Contactez le support Tout y est afin de vérifier votre identité et récupérer l’accès à votre compte.",
        confirmButtonText: "Compris",
      });
    }
  });

  view.addEventListener("submit", (event) => {
    const form = event.target.closest("[data-auth-form]");

    if (!form) {
      return;
    }

    const fields = [...form.querySelectorAll("[data-validate]")];
    const valid = fields.map((input) => {
      input.dataset.touched = "true";
      return validateField(input, true);
    }).every(Boolean);

    if (!valid) {
      event.preventDefault();
      fields.find((input) => !input.checkValidity())?.focus();
      return;
    }

    setLoading(form);
  });

  if (window.location.hash === "#inscription" && view.dataset.activeMode !== "register") {
    switchPanel("register");
  }
})();
