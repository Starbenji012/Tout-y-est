// Présentation d'un select simple : la valeur réelle et la soumission restent natives.
(() => {
  const controls = new WeakMap();
  let opened = null;
  document.addEventListener('pointerdown', event => {
    if (opened && !opened.root.contains(event.target)) opened.close();
  });
  class SelectControl {
    static enhance(select) {
      if (controls.has(select)) return controls.get(select);
      if (!select.id || select.multiple || select.size > 1) return null;
      const label = select.labels?.[0];
      const caption = label?.querySelector('[data-select-label]');
      // Sans libellé identifiable, conserver le contrôle natif accessible.
      if (!caption?.id) return null;
      const control = new SelectControl(select, label, caption);
      controls.set(select, control);
      return control;
    }
    constructor(select, label, caption) {
      this.select = select;
      this.root = document.createElement('span'); this.root.className = 'select-control';
      this.trigger = document.createElement('button'); this.trigger.type = 'button';
      this.trigger.className = 'select-control__trigger'; this.trigger.id = select.id + '-trigger';
      this.trigger.setAttribute('role', 'combobox');
      this.trigger.setAttribute('aria-haspopup', 'listbox');
      this.trigger.setAttribute('aria-expanded', 'false');
      this.trigger.setAttribute('aria-controls', select.id + '-options');
      this.value = document.createElement('span'); this.value.id = select.id + '-value';
      this.trigger.setAttribute('aria-labelledby', caption.id + ' ' + this.value.id);
      const description = select.getAttribute('aria-describedby');
      if (description) this.trigger.setAttribute('aria-describedby', description);
      this.trigger.append(this.value);
      this.list = document.createElement('span'); this.list.id = select.id + '-options';
      this.list.className = 'select-control__options'; this.list.hidden = true;
      this.list.setAttribute('role', 'listbox'); this.list.setAttribute('aria-labelledby', caption.id);
      this.options = [...select.options].map((option, index) => {
        const node = document.createElement('span'); node.id = select.id + '-option-' + index;
        node.className = 'select-control__option'; node.setAttribute('role', 'option');
        node.textContent = option.textContent;
        node.setAttribute('aria-disabled', String(option.disabled));
        node.addEventListener('click', event => { event.preventDefault(); this.choose(index); });
        node.addEventListener('pointermove', () => { if (!option.disabled) this.activate(index); });
        this.list.append(node); return node;
      });
      this.trigger.addEventListener('click', () => { if (this.list.hidden) this.open(); else this.close(); });
      this.trigger.addEventListener('keydown', event => this.keydown(event));
      this.root.addEventListener('focusout', event => { if (!this.root.contains(event.relatedTarget)) this.close(); });
      select.addEventListener('change', () => this.sync());
      this.root.append(this.trigger, this.list); select.after(this.root);
      // N'enlever le select du parcours clavier qu'après construction complète.
      select.hidden = true; label.htmlFor = this.trigger.id; this.sync();
    }
    sync() {
      this.value.textContent = this.select.selectedOptions[0]?.textContent || '';
      this.trigger.disabled = this.select.disabled;
      this.options.forEach((node, index) => node.setAttribute('aria-selected', String(index === this.select.selectedIndex)));
      if (this.select.disabled) this.close();
    }
    focus() { this.trigger.focus(); }
    activate(index) {
      this.active = index;
      this.options.forEach((node, i) => node.classList.toggle('is-active', i === index));
      this.trigger.setAttribute('aria-activedescendant', this.options[index].id);
      this.options[index].scrollIntoView?.({block: 'nearest'});
    }
    open() {
      if (this.trigger.disabled) return;
      if (opened && opened !== this) opened.close();
      const selected = this.select.selectedIndex;
      const index = selected >= 0 && !this.select.options[selected].disabled ? selected : [...this.select.options].findIndex(option => !option.disabled);
      if (index < 0) return;
      opened = this; this.list.hidden = false; this.trigger.setAttribute('aria-expanded', 'true'); this.activate(index);
    }
    close() {
      this.list.hidden = true; this.trigger.setAttribute('aria-expanded', 'false');
      this.trigger.removeAttribute('aria-activedescendant');
      if (opened === this) opened = null;
    }
    choose(index) {
      if (this.select.disabled || this.select.options[index].disabled) return;
      this.select.selectedIndex = index; this.close(); this.sync(); this.focus();
      this.select.dispatchEvent(new Event('change', {bubbles: true}));
    }
    keydown(event) {
      if (event.key === 'Tab') { this.close(); return; }
      if (event.key === 'Escape') { event.preventDefault(); this.close(); return; }
      if (['Enter', ' '].includes(event.key)) {
        event.preventDefault(); if (this.list.hidden) this.open(); else this.choose(this.active); return;
      }
      if (!['ArrowDown','ArrowUp','Home','End'].includes(event.key)) return;
      event.preventDefault();
      if (this.list.hidden) { this.open(); return; }
      const enabled = [...this.select.options].map((option, i) => option.disabled ? -1 : i).filter(i => i >= 0);
      const current = enabled.indexOf(this.active);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length;
      this.activate(enabled[next]);
    }
  }
  window.SelectControl = SelectControl;
})();
