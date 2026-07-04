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

// Checkout: bank account selection
document.querySelectorAll('.bank-card input[type=radio]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.bank-card').forEach(c => c.classList.remove('selected'));
    if (radio.checked) radio.closest('.bank-card').classList.add('selected');
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

  const renderSuggest = (results, query) => {
    if (!results.length) {
      searchSuggest.innerHTML = '<div class="ss-empty">No matches for “' + query.replace(/</g, '&lt;') + '”. Press Enter to search anyway.</div>';
      searchSuggest.classList.add('open');
      return;
    }
    searchSuggest.innerHTML = results.map(r =>
      '<a href="' + r.url + '">' +
        '<img src="' + r.image + '" alt="">' +
        '<span class="ss-info"><span class="ss-name">' + r.name + '</span>' +
        (r.category ? '<span class="ss-cat">' + r.category + '</span>' : '') + '</span>' +
        '<span class="ss-price">' + r.price + '</span>' +
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
