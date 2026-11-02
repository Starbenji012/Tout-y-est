// Le serveur reste autoritaire ; les formulaires fonctionnent aussi sans JavaScript.
(() => {
  const forms = [...document.querySelectorAll('[data-courier-form]')];
  let submitting = false;
  const reset = () => {
    submitting = false;
    forms.forEach(form => { form.removeAttribute('aria-busy'); form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; }); });
  };
  forms.forEach(form => form.addEventListener('submit', event => {
    if (submitting) { event.preventDefault(); return; }
    submitting = true;form.setAttribute('aria-busy','true');
    forms.forEach(item => item.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; }));
  }));
  window.addEventListener('pageshow', reset);
})();
