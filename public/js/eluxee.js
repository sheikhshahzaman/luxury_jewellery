// Mobile menu
const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (menuToggle && mobileMenu) {
  menuToggle.addEventListener('click', () => mobileMenu.classList.toggle('open'));
  mobileMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mobileMenu.classList.remove('open')));
}

// Search bar toggle
const searchToggle = document.getElementById('searchToggle');
const searchBar = document.getElementById('searchBar');
if (searchToggle && searchBar) {
  searchToggle.addEventListener('click', () => {
    searchBar.classList.toggle('open');
    if (searchBar.classList.contains('open')) searchBar.querySelector('input').focus();
  });
}

// Flash messages auto-dismiss
const flashStack = document.getElementById('flashStack');
if (flashStack) {
  setTimeout(() => {
    flashStack.style.transition = 'opacity .5s';
    flashStack.style.opacity = '0';
    setTimeout(() => flashStack.remove(), 500);
  }, 4500);
}

// Quantity steppers (product page + cart)
document.querySelectorAll('.qty-box').forEach(box => {
  const input = box.querySelector('input');
  const submitOnChange = box.dataset.autosubmit === '1';
  box.querySelectorAll('button[data-step]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const step = parseInt(btn.dataset.step, 10);
      const max = parseInt(input.max || '999', 10);
      const next = Math.min(Math.max(1, (parseInt(input.value, 10) || 1) + step), max);
      input.value = next;
      if (submitOnChange) input.closest('form').submit();
    });
  });
});

// Product gallery thumbnails
const pdMainImg = document.querySelector('.pd-main img');
document.querySelectorAll('.pd-thumbs button').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.pd-thumbs button').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    pdMainImg.style.opacity = '0';
    setTimeout(() => {
      pdMainImg.src = btn.dataset.src;
      pdMainImg.style.opacity = '1';
    }, 180);
  });
});

// Checkout: account selection inside the visible payment panel
const syncBankCards = () => {
  document.querySelectorAll('.bank-card').forEach(c => c.classList.toggle('selected', c.querySelector('input').checked));
};
document.querySelectorAll('.bank-card input[type=radio]').forEach(radio => radio.addEventListener('change', syncBankCards));

// Checkout: payment method switch (panels are shown by CSS :has)
const checkoutForm = document.getElementById('checkoutForm');
if (checkoutForm) {
  const proofInputs = checkoutForm.querySelectorAll('.proof-section input');
  const totalEl = document.getElementById('coTotal');
  const applyMethod = () => {
    const method = checkoutForm.querySelector('input[name=payment_method]:checked')?.value;
    const isCod = method === 'cod';
    // COD sends no receipt fields at all.
    proofInputs.forEach(i => { i.disabled = isCod; });
    if (totalEl) totalEl.textContent = isCod ? totalEl.dataset.totalCod : totalEl.dataset.total;
    // Pick the first account of the chosen method unless one is already picked there.
    const panel = checkoutForm.querySelector('.pay-panel[data-method="' + method + '"]');
    const radios = panel ? panel.querySelectorAll('input[name=bank_account_id]') : [];
    if (radios.length && ![...radios].some(r => r.checked)) radios[0].checked = true;
    syncBankCards();
  };
  checkoutForm.querySelectorAll('input[name=payment_method]').forEach(r => r.addEventListener('change', applyMethod));
  applyMethod();
}

// Copy buttons (account numbers, IBAN)
document.querySelectorAll('[data-copy]').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    const done = () => { const t = btn.textContent; btn.textContent = 'Copied'; btn.classList.add('copied'); setTimeout(() => { btn.textContent = t; btn.classList.remove('copied'); }, 1400); };
    (navigator.clipboard ? navigator.clipboard.writeText(btn.dataset.copy) : Promise.reject()).then(done).catch(done);
  });
});

// Checkout: payment screenshot preview
const upBox = document.getElementById('uploadBox');
if (upBox) {
  const upInput = upBox.querySelector('input[type=file]');
  const preview = document.getElementById('uploadPreview');
  const title = upBox.querySelector('.up-title');
  upBox.addEventListener('click', () => upInput.click());
  upInput.addEventListener('change', () => {
    const file = upInput.files[0];
    if (!file) return;
    upBox.classList.add('has-file');
    title.textContent = file.name;
    if (file.type.startsWith('image/') && preview) {
      const reader = new FileReader();
      reader.onload = ev => { preview.src = ev.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(file);
    } else if (preview) {
      preview.style.display = 'none';
    }
  });
}

// Live search suggestions
const searchInput = document.getElementById('searchInput');
const searchSuggest = document.getElementById('searchSuggest');
if (searchInput && searchSuggest) {
  let timer = null;
  let lastQuery = '';

  // Everything from the server is escaped before it goes into innerHTML.
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const renderSuggest = (results, query) => {
    if (!results.length) {
      searchSuggest.innerHTML = '<div class="ss-empty">No matches for “' + esc(query) + '”. Press Enter to search anyway.</div>';
      searchSuggest.classList.add('open');
      return;
    }
    searchSuggest.innerHTML = results.map(r =>
      '<a href="' + esc(r.url) + '">' +
        '<img src="' + esc(r.image) + '" alt="">' +
        '<span class="ss-info"><span class="ss-name">' + esc(r.name) + '</span>' +
        (r.category ? '<span class="ss-cat">' + esc(r.category) + '</span>' : '') + '</span>' +
        '<span class="ss-price">' + esc(r.price) + '</span>' +
      '</a>'
    ).join('') + '<div class="ss-all">Press Enter for all results</div>';
    searchSuggest.classList.add('open');
  };

  const fetchSuggest = (query) => {
    fetch(searchInput.dataset.suggestUrl + '?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => { if (searchInput.value.trim() === query) renderSuggest(data.results || [], query); })
      .catch(() => {});
  };

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim();
    clearTimeout(timer);
    if (query.length < 2) { searchSuggest.classList.remove('open'); return; }
    if (query === lastQuery) return;
    lastQuery = query;
    timer = setTimeout(() => fetchSuggest(query), 220);
  });

  document.addEventListener('click', e => {
    if (!searchSuggest.contains(e.target) && e.target !== searchInput) searchSuggest.classList.remove('open');
  });
  searchInput.addEventListener('keydown', e => { if (e.key === 'Escape') searchSuggest.classList.remove('open'); });
}

// Copy coupon codes on the offers page
document.querySelectorAll('.offer-copy').forEach(btn => {
  btn.addEventListener('click', () => {
    const code = btn.dataset.code;
    const done = () => { const t = btn.textContent; btn.textContent = 'Copied ✓'; btn.classList.add('copied'); setTimeout(() => { btn.textContent = t; btn.classList.remove('copied'); }, 1600); };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(code).then(done).catch(done);
    } else {
      const ta = document.createElement('textarea');
      ta.value = code; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta); done();
    }
  });
});

// Scroll reveal
const io = new IntersectionObserver(entries => {
  entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));

// Product options: live price, chosen labels, engraving preview, metal events for the 3D viewer
const pdOptions = document.querySelector('.pd-options');
const pdPrice = document.getElementById('pdPrice');
if (pdOptions && pdPrice) {
  const base = parseFloat(pdOptions.dataset.basePrice) || 0;
  const engraveFee = parseFloat(pdOptions.dataset.engravingFee) || 0;
  const symbol = pdPrice.dataset.symbol || '$';
  const fmt = n => symbol + (Number.isInteger(Math.round(n * 100) / 100)
    ? Math.round(n).toLocaleString('en-US')
    : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  const engraving = pdOptions.querySelector('input[name=engraving]');
  const counter = pdOptions.querySelector('[data-engrave-count]');
  const preview = pdOptions.querySelector('[data-engrave-preview]');

  const update = () => {
    let price = base;
    const metal = pdOptions.querySelector('input[name=metal]:checked');
    if (metal) price += parseFloat(metal.dataset.price) || 0;
    if (engraving && engraving.value.trim() !== '') price += engraveFee;
    pdPrice.textContent = fmt(Math.max(0, price));

    ['metal', 'size'].forEach(name => {
      const chosen = pdOptions.querySelector('[data-chosen="' + name + '"]');
      const input = pdOptions.querySelector('input[name=' + name + ']:checked');
      if (chosen) chosen.textContent = input ? input.value : '';
    });
    if (engraving) {
      if (counter) counter.textContent = engraving.value.length;
      if (preview) preview.textContent = engraving.value;
    }
  };

  pdOptions.addEventListener('change', e => {
    update();
    if (e.target.name === 'metal') {
      document.dispatchEvent(new CustomEvent('eluxee:metal', { detail: { tone: e.target.dataset.tone, name: e.target.value } }));
    }
  });
  if (engraving) engraving.addEventListener('input', update);
  update();
}

// Header categories dropdown: hover/focus on desktop, tap on touch, Esc to close
document.querySelectorAll('[data-dropdown]').forEach(dd => {
  const btn = dd.querySelector('.nav-drop-btn');
  const setOpen = open => { dd.classList.toggle('open', open); btn.setAttribute('aria-expanded', String(open)); };
  let closeTimer = null;
  const canHover = window.matchMedia('(hover: hover)').matches;

  if (canHover) {
    dd.addEventListener('mouseenter', () => { clearTimeout(closeTimer); setOpen(true); });
    dd.addEventListener('mouseleave', () => { closeTimer = setTimeout(() => setOpen(false), 160); });
  }
  btn.addEventListener('click', () => setOpen(!dd.classList.contains('open')));
  dd.addEventListener('focusout', e => { if (!dd.contains(e.relatedTarget)) setOpen(false); });
  dd.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
  document.addEventListener('click', e => { if (!dd.contains(e.target)) setOpen(false); });
});

// Asset cache (public/sw.js): keeps the 3D engine, styles and scripts on the device after the first visit.
const swUrl = document.documentElement.dataset.sw;
if (swUrl && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register(swUrl).catch(() => {}));
}

// Live rates: one shared request, every poll_seconds on the rates page and
// half as often elsewhere (for the announcement-bar ticker). The server only
// returns stored values: spot from the scheduled fetch, prices from Admin.
//
// Between requests the numbers on screen shuffle a little so the board feels
// live (Admin can turn this off). Every shuffled value is the real value plus
// a small offset worked out fresh each tick, never added to the last shown
// number, so it cannot drift; a new real value re-centres it at once.
//   - rupee prices: Rs min–max above or below; buy and sell move together,
//     so the spread stays exact;
//   - spot (USD): only the cents move; the whole dollars never change.
// Offsets come from a hash of (item, time tick), so every visitor sees the
// same numbers at the same moment.
const fnv = text => {
  let h = 0x811c9dc5;
  for (let i = 0; i < text.length; i++) {
    h ^= text.charCodeAt(i);
    h = Math.imul(h, 0x01000193);
  }
  return h >>> 0;
};
const pick = (seed, [lo, hi]) => lo + (fnv(seed) % (hi - lo + 1));
const sign = seed => (fnv(seed) % 2 ? 1 : -1);

/** The real snapshot as shown at time tick `tick` (pure; real data is not modified). */
function shuffleRates(real, tick) {
  const d = real.display || {};
  if (!d.shuffle) return real;
  const pkr = d.pkr || [10, 20];
  // Every item of a metal moves the same way in a tick, as the market does.
  const rupees = (key, metal, t) => sign(`dir:${metal}:${t}`) * pick(`pkr:${key}:${t}`, pkr);

  const prices = (real.prices || []).map(row => {
    const now = rupees(row.key, row.metal, tick);
    return { ...row, buy: row.buy + now, sell: row.sell + now, change: now - rupees(row.key, row.metal, tick - 1) };
  });

  const spot = { ...(real.spot || {}) };
  ['gold', 'silver'].forEach(metal => {
    const s = spot[metal];
    if (!s || !s.price) return;
    const cents = (d.cents && d.cents[metal]) || [1, 5];
    const offset = sign(`spot-dir:${metal}:${tick}`) * pick(`spot:${metal}:${tick}`, cents) / 100;
    const whole = Math.floor(s.price);
    // Stay within the same whole dollar: if +offset would cross it, go the other way.
    let price = Math.round((s.price + offset) * 100) / 100;
    if (Math.floor(price) !== whole) price = Math.round((s.price - offset) * 100) / 100;
    if (Math.floor(price) !== whole) price = s.price;
    const moved = price - s.price;
    const base = s.price - s.change; // the price ~24h ago, unchanged
    const tola = s.pkr_per_tola == null ? null : s.pkr_per_tola + rupees(`spot-tola-${metal}`, `spot-${metal}`, tick);
    const gram = s.pkr_per_gram == null ? null : s.pkr_per_gram + rupees(`spot-gram-${metal}`, `spot-${metal}`, tick);
    spot[metal] = {
      ...s,
      price,
      bid_usd_oz: Math.round((s.bid_usd_oz + moved) * 100) / 100,
      ask_usd_oz: Math.round((s.ask_usd_oz + moved) * 100) / 100,
      change: Math.round((s.change + moved) * 100) / 100,
      change_pct: base > 0 ? ((s.change + moved) / base) * 100 : 0,
      pkr_per_tola: tola,
      pkr_per_gram: gram,
      history: (s.history || []).length ? [...s.history.slice(0, -1), price] : s.history,
    };
  });

  return { ...real, prices, spot };
}
window.laxRates = { shuffle: shuffleRates };

const ratesSource = document.querySelector('[data-rates]') || document.querySelector('[data-rate-ticker]');
if (ratesSource) {
  const endpoint = ratesSource.dataset.endpoint;
  const factor = document.querySelector('[data-rates]') ? 1 : 2;
  const show = data => document.dispatchEvent(new CustomEvent('lux:rates', { detail: data }));
  let real = null;
  let lastTick = null;

  // Shuffle tick: re-draw whenever the time tick changes.
  const tickNow = () => (real && real.display ? Math.floor(Date.now() / (real.display.seconds * 1000)) : null);
  setInterval(() => {
    if (!real || document.hidden || !(real.display && real.display.shuffle)) return;
    const tick = tickNow();
    if (tick === lastTick) return;
    lastTick = tick;
    show(shuffleRates(real, tick));
  }, 400);

  let timer = null;
  const schedule = ms => { clearTimeout(timer); timer = setTimeout(poll, Math.max(5000, ms)); };
  function poll() {
    if (document.hidden) return;
    fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        real = data;
        lastTick = tickNow();
        show(data.display && data.display.shuffle ? shuffleRates(data, lastTick) : data);
        schedule((data.poll_seconds || 30) * 1000 * factor);
      })
      .catch(() => schedule(60000));
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  poll(); // real values straight away, so the shuffle can start

  // Announcement-bar ticker
  document.addEventListener('lux:rates', ({ detail }) => {
    (detail.prices || []).forEach(row => {
      document.querySelectorAll(`[data-ticker-row="${row.key}"]`).forEach(link => {
        const price = link.querySelector('[data-ticker-price]');
        const text = 'Rs ' + Math.round(row.buy).toLocaleString('en-US');
        if (!price || price.textContent === text) return;
        link.classList.toggle('is-up', row.change >= 0);
        link.classList.toggle('is-down', row.change < 0);
        price.textContent = text;
        price.classList.remove('bump');
        void price.offsetWidth;
        price.classList.add('bump');
      });
    });
  });
}

// Testimonial carousel: cards on a curved rail, the active one in the spotlight.
document.querySelectorAll('[data-testimonials]').forEach(stage => {
  const section = stage.closest('.tw');
  const cards = [...stage.querySelectorAll('[data-tw-card]')];
  const n = cards.length;
  if (!n) return;

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const current = section.querySelector('[data-tw-current]');
  const progress = section.querySelector('[data-tw-progress]');
  const DURATION = 6500;
  let active = 0;
  let timer = null;
  let paused = false;

  // Split each quote into words so they can rise in one after another.
  cards.forEach(card => {
    const q = card.querySelector('[data-tw-quote]');
    const words = q.textContent.trim().split(/\s+/);
    q.setAttribute('aria-label', q.textContent.trim());
    q.innerHTML = words.map((w, i) => `<span class="w" style="--i:${Math.min(i, 40)}" aria-hidden="true">${w.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))} </span>`).join('');
  });

  // The stage is as tall as the tallest card (quotes differ in length), so no empty gap.
  const fitHeight = () => {
    const tallest = Math.max(...cards.map(c => c.offsetHeight));
    if (tallest > 0) stage.style.height = (tallest + 70) + 'px';
  };
  window.addEventListener('resize', fitHeight);
  if (document.fonts) document.fonts.ready.then(fitHeight);

  const layout = () => {
    cards.forEach((card, i) => {
      // Shortest way round the loop, so the rail feels endless.
      let o = i - active;
      if (o > n / 2) o -= n;
      if (o < -n / 2) o += n;
      const a = Math.abs(o);
      card.style.setProperty('--o', o);
      card.style.setProperty('--a', a);
      card.style.zIndex = String(20 - a);
      card.classList.toggle('is-far', a > 2);
      card.setAttribute('aria-hidden', String(a !== 0));
      if (a === 0 && !card.classList.contains('is-active')) {
        card.classList.add('is-active');
      } else if (a !== 0) {
        card.classList.remove('is-active');
      }
    });
    if (current) current.textContent = active + 1;
  };

  const go = i => {
    active = (i + n) % n;
    layout();
    restart();
  };

  const restart = () => {
    clearTimeout(timer);
    if (n < 2 || reduced) return;
    if (progress) {
      progress.classList.remove('run');
      void progress.offsetWidth;
      progress.style.setProperty('--dur', DURATION + 'ms');
      progress.classList.add('run');
    }
    if (!paused) timer = setTimeout(() => go(active + 1), DURATION);
  };

  const pause = on => {
    paused = on;
    section.classList.toggle('is-paused', on);
    if (on) clearTimeout(timer);
    else restart();
  };

  section.querySelector('[data-tw-prev]')?.addEventListener('click', () => go(active - 1));
  section.querySelector('[data-tw-next]')?.addEventListener('click', () => go(active + 1));
  cards.forEach((card, i) => card.addEventListener('click', () => { if (i !== active) go(i); }));
  stage.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight') { e.preventDefault(); go(active + 1); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); go(active - 1); }
  });

  // Swipe / drag
  let startX = null;
  stage.addEventListener('pointerdown', e => { startX = e.clientX; });
  stage.addEventListener('pointerup', e => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 50) go(active + (dx < 0 ? 1 : -1));
  });

  // Pause while hovered or focused, and while off-screen.
  section.addEventListener('mouseenter', () => pause(true));
  section.addEventListener('mouseleave', () => pause(false));
  section.addEventListener('focusin', () => pause(true));
  section.addEventListener('focusout', e => { if (!section.contains(e.relatedTarget)) pause(false); });
  new IntersectionObserver(([entry]) => pause(!entry.isIntersecting), { threshold: 0.25 }).observe(section);

  layout();
  fitHeight();
});

// Shop: fold the price filter away on phones/tablets unless a price filter is active.
document.querySelectorAll('[data-collapse-mobile]').forEach(d => {
  if (window.matchMedia('(max-width: 1024px)').matches && !d.hasAttribute('data-keep-open')) d.open = false;
  d.querySelector('summary')?.addEventListener('click', e => {
    if (!window.matchMedia('(max-width: 1024px)').matches) e.preventDefault(); // always open on desktop
  });
});

// Account / shop chip rows: bring the active pill into view.
document.querySelectorAll('.side-nav a.on, .filter-list a.on').forEach(a => {
  const row = a.parentElement;
  if (row.scrollWidth > row.clientWidth) {
    row.scrollLeft += a.getBoundingClientRect().left - row.getBoundingClientRect().left - 24;
  }
});

// Admin menu row on small screens: keep the current section in view.
const admOn = document.querySelector('.adm-nav a.on');
if (admOn && admOn.parentElement.scrollWidth > admOn.parentElement.clientWidth) {
  const row = admOn.parentElement;
  row.scrollLeft += admOn.getBoundingClientRect().left - row.getBoundingClientRect().left - 16;
}
