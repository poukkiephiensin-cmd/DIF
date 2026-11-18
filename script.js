'use strict';
(() => {
  const $ = (s, r = document) => r.querySelector(s),
    $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const nav = $('#main-nav'),
    menu = $('[data-menu]'),
    search = $('#search-panel'),
    op = $('[data-search]');
  const closeNav = () => {
    nav?.classList.remove('open');
    menu?.setAttribute('aria-expanded', 'false')
  };
  const closeSearch = () => {
    if (search) search.hidden = true;
    op?.setAttribute('aria-expanded', 'false')
  };
  menu?.addEventListener('click', () => {
    closeSearch();
    menu.setAttribute('aria-expanded', String(nav.classList.toggle('open')))
  });
  op?.addEventListener('click', () => {
    closeNav();
    search.hidden = !search.hidden;
    op.setAttribute('aria-expanded', String(!search.hidden));
    if (!search.hidden) $('input', search).focus()
  });
  $('[data-close-search]')?.addEventListener('click', () => {
    closeSearch();
    op.focus()
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      const wasOpen = search && !search.hidden;
      closeSearch();
      closeNav();
      if (wasOpen) op.focus()
    }
  });
  let idx = 0;
  const slides = $$('[data-slide]');

  function show(i) {
    idx = (i + slides.length) % slides.length;
    slides.forEach((el, j) => {
      el.hidden = j !== idx;
      el.classList.toggle('entering', j === idx)
    });
    $$('[data-slide-to]').forEach((b, j) => b.setAttribute('aria-current', String(j === idx)));
    $('[data-slide-index]').textContent = String(idx + 1).padStart(2, '0')
  }
  $('[data-next]')?.addEventListener('click', () => show(idx + 1));
  $('[data-prev]')?.addEventListener('click', () => show(idx - 1));
  $$('[data-slide-to]').forEach(b => b.addEventListener('click', () => show(+b.dataset.slideTo)));

  function tab(id) {
    const btn = $(`[data-tab="${id}"]`);
    if (!btn) return;
    $$('[data-tabpanel]').forEach(el => el.hidden = el.id !== id);
    $$('[data-tab]').forEach(el => el.setAttribute('aria-pressed', String(el === btn)));
    $('[data-edit-heading]').textContent = btn.dataset.title
  }
  $$('[data-tab]').forEach(b => b.addEventListener('click', () => tab(b.dataset.tab)));
  $$('[data-arrivals]').forEach(a => a.addEventListener('click', () => tab('edit-0')));
  $$('[data-step]').forEach(b => b.addEventListener('click', () => {
    const n = $('input', b.closest('.stepper'));
    n.value = String(Math.max(+n.min || 1, Math.min(+n.max || 99, (Number(n.value) || 1) + Number(b.dataset.step))));
    n.dispatchEvent(new Event('change', {
      bubbles: true
    }))
  }));
  $$('[data-dismiss]').forEach(b => b.onclick = () => b.parentElement.remove());
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault()
  }));
  const same = $('[name="same_shipping"]');

  function toggleShip() {
    if (!same) return;
    const p = $('[data-shipping-fields]');
    p.hidden = same.checked;
    $$('input,textarea', p).forEach(n => {
      n.required = !same.checked;
      n.disabled = same.checked
    })
  }
  same?.addEventListener('change', toggleShip);
  toggleShip();
  $$('[name="service"]').forEach(s => s.addEventListener('change', () => {
    const fee = Number(s.dataset.cents),
      gr = $('[data-grand]');
    const lak = n => (n / 100).toLocaleString('en-US', {
      maximumFractionDigits: 2
    }) + ' ₭';
    $('[data-fee]').textContent = lak(fee);
    gr.textContent = lak(Number(gr.dataset.sub) + fee)
  }));
  $$('form[data-once]').forEach(f => f.addEventListener('submit', () => {
    const b = $('button[type="submit"]', f);
    if (b) {
      b.disabled = true;
      b.textContent = 'Placing order…'
    }
  }));
})();
