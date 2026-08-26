(() => {
  const defaultRules = {
    required: (input) => input.value.trim() === "" ? "Ce champ est obligatoire." : "",
    name: (input) => input.value.trim().length >= 2 ? "" : "Saisissez au moins 2 caractères.",
    email: (input) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim()) ? "" : "Saisissez une adresse e-mail valide.",
    password: (input) => input.value.length >= 8 && /[A-Za-z]/.test(input.value) && /\d/.test(input.value)
      ? ""
      : "Utilisez au moins 8 caractères, une lettre et un chiffre.",
    confirmation: (input, root) => {
      const reference = root.querySelector(input.dataset.match || "[data-register-password]");
      return input.value !== "" && input.value === reference?.value ? "" : "Les mots de passe ne correspondent pas.";
    },
  };

  const create = (root, customRules = {}) => {
    const rules = { ...defaultRules, ...customRules };
    const messageElement = (input) => input.closest(".account-field")?.querySelector("[data-field-error]");

    const messageFor = (input) => {
      if (input.required && input.value.trim() === "") {
        return defaultRules.required(input);
      }

      return rules[input.dataset.validate]?.(input, root) || "";
    };

    const validateField = (input, force = false) => {
      if (!force && input.dataset.touched !== "true") {
        return true;
      }

      const message = messageFor(input);
      const feedback = messageElement(input);
      const valid = message === "";
      input.setCustomValidity(message);
      input.setAttribute("aria-invalid", String(!valid));
      input.classList.toggle("is-invalid", !valid);
      input.classList.toggle("is-valid", valid && input.value.trim() !== "");

      if (feedback) {
        feedback.textContent = message;
        feedback.dataset.validationState = valid ? "success" : "error";
      }

      return valid;
    };

    const validateForm = (form) => {
      const fields = [...form.querySelectorAll("[data-validate]")];
      const valid = fields.map((input) => {
        input.dataset.touched = "true";
        return validateField(input, true);
      }).every(Boolean);

      return { valid, fields };
    };

    return Object.freeze({ validateField, validateForm });
  };

  window.ValidationSystem = Object.freeze({ create });
})();
