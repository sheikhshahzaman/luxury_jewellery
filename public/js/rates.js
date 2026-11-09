// Live rates page: rolling digits, sparklines, countdown ring and row flashes.
// Data arrives as a `lux:rates` event from the shared poller in eluxee.js:
// `spot` (fetched market prices) and `prices` (the store's own, from Admin).
(() => {
  const root = document.querySelector('[data-rates]');
  if (!root) return;

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fmtRs = n => 'Rs ' + Math.round(n).toLocaleString('en-US');
  const fmtUsd = n => '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const isDigit = c => c >= '0' && c <= '9';

  // ---------------------------------------------------------------------
  // Rolling digits: each digit is a vertical 0–9 strip that slides into place.
  // Screen readers get the plain number from a visually hidden copy.
  // ---------------------------------------------------------------------
  const shape = text => text.replace(/\d/g, '0');

  function buildRoll(el, text, fromZero) {
    el.textContent = '';
    const sr = document.createElement('span');
    sr.className = 'sr-only';
    sr.textContent = text;
    const roll = document.createElement('span');
    roll.className = 'roll';
    roll.setAttribute('aria-hidden', 'true');

    [...text].forEach((c, i) => {
      if (!isDigit(c)) {
        const ch = document.createElement('span');
        ch.className = 'ch';
        ch.textContent = c;
        roll.appendChild(ch);
        return;
      }
      const dg = document.createElement('span');
      dg.className = 'dg';
      const strip = document.createElement('span');
      strip.className = 'dg-strip';
      for (let d = 0; d <= 9; d++) {
        const s = document.createElement('span');
        s.textContent = d;
        strip.appendChild(s);
      }
      strip.style.transitionDelay = (i * 35) + 'ms';
      strip.style.transform = `translateY(-${(fromZero ? 0 : +c) * 1.15}em)`;
      dg.appendChild(strip);
      roll.appendChild(dg);
    });

    el.append(sr, roll);
    el._text = text;
    el._sr = sr;
    el._strips = [...roll.querySelectorAll('.dg-strip')];
    // Roll up from zero: force a layout at 0, then set the real digits. (Not via
    // requestAnimationFrame, which never fires in a hidden tab and would leave 0s.)
    if (fromZero) {
      void roll.offsetWidth;
      setRoll(el, text);
    }
  }

  function setRoll(el, text) {
    if (!el._strips || shape(el._text) !== shape(text)) {
      buildRoll(el, text, false);
      return;
    }
    let k = 0;
    [...text].forEach(c => {
      if (isDigit(c)) el._strips[k++].style.transform = `translateY(-${+c * 1.15}em)`;
    });
    el._text = text;
    el._sr.textContent = text;
  }

  // Upgrade server-rendered numbers; on first view they roll up from zero.
  root.querySelectorAll('[data-price],[data-buy],[data-sell]').forEach(el => buildRoll(el, el.textContent.trim(), !reduced));

  // ---------------------------------------------------------------------
  // Sparklines
  // ---------------------------------------------------------------------
  function drawSpark(svg, history, animate) {
    if (!svg || !history || history.length < 2) return;
    const w = 320, h = 90, pad = 10;
    const min = Math.min(...history), max = Math.max(...history);
    const span = max - min || 1;
    const pts = history.map((v, i) => [
      (i / (history.length - 1)) * w,
      pad + (1 - (v - min) / span) * (h - pad * 2),
    ]);
    // Smooth the line with midpoint quadratic curves.
    let d = `M${pts[0][0]},${pts[0][1]}`;
    for (let i = 1; i < pts.length; i++) {
      const [x0, y0] = pts[i - 1], [x1, y1] = pts[i];
      d += ` Q${x0},${y0} ${(x0 + x1) / 2},${(y0 + y1) / 2}`;
    }
    const last = pts[pts.length - 1];
    d += ` T${last[0]},${last[1]}`;

    const line = svg.querySelector('.spark-line');
    line.setAttribute('d', d);
    svg.querySelector('.spark-fill').setAttribute('d', `${d} L${w},${h} L0,${h} Z`);
    const dot = svg.querySelector('.spark-dot');
    dot.setAttribute('cx', last[0]);
    dot.setAttribute('cy', last[1]);

    if (animate && !reduced) {
      line.style.setProperty('--len', line.getTotalLength());
      line.classList.add('draw');
    } else {
      line.classList.remove('draw');
    }
  }

  root.querySelectorAll('[data-spot]').forEach(card => {
    const svg = card.querySelector('[data-spark]');
    drawSpark(svg, JSON.parse(svg.dataset.spark || '[]'), true);
  });

  // ---------------------------------------------------------------------
  // Countdown ring to the next update
  // ---------------------------------------------------------------------
  const ring = root.querySelector('[data-tick-ring]');
  const runRing = ms => {
    if (!ring || reduced) return;
    ring.classList.remove('run');
    void ring.getBoundingClientRect(); // restart the animation
    ring.style.setProperty('--tick', ms + 'ms');
    ring.classList.add('run');
  };
  runRing(parseInt(root.dataset.nextIn || '30000', 10));

  // The time shown is when the spot values were recorded, not when we last asked.
  const updated = root.querySelector('[data-updated]');
  const stamp = iso => {
    if (!updated || !iso) return;
    updated.dateTime = iso;
    updated.textContent = new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };
  // The server renders its own (UTC) time; show the visitor's local time straight away.
  stamp(updated?.getAttribute('datetime'));

  // ---------------------------------------------------------------------
  // Apply each new snapshot
  // ---------------------------------------------------------------------
  document.addEventListener('lux:rates', ({ detail: data }) => {
    const spot = data.spot || {};
    stamp(spot.updated_at);
    runRing(((data.display && data.display.shuffle ? data.display.seconds : data.poll_seconds) || 30) * 1000);

    ['gold', 'silver'].forEach(metal => {
      const s = spot[metal];
      const card = root.querySelector(`[data-spot="${metal}"]`);
      if (!card || !s) return;
      setRoll(card.querySelector('[data-price]'), fmtUsd(s.price));
      const chip = card.querySelector('[data-change]');
      const up = s.change >= 0;
      chip.classList.toggle('is-up', up);
      chip.classList.toggle('is-down', !up);
      chip.querySelector('[data-change-text]').textContent =
        `${up ? '+' : '−'}${fmtUsd(Math.abs(s.change))} (${Math.abs(s.change_pct).toFixed(2)}%)`;
      card.querySelector('[data-bidask-row]').hidden = s.bid_usd_oz === s.ask_usd_oz;
      card.querySelector('[data-bidask]').textContent = `${fmtUsd(s.bid_usd_oz)} / ${fmtUsd(s.ask_usd_oz)}`;
      card.querySelector('[data-pkr-row]').hidden = !s.pkr_per_tola;
      if (s.pkr_per_tola) card.querySelector('[data-pkr]').textContent = `${fmtRs(s.pkr_per_tola)} / tola · ${fmtRs(s.pkr_per_gram)} / gram`;
      drawSpark(card.querySelector('[data-spark]'), s.history, false);
    });

    (data.prices || []).forEach(row => {
      const tr = root.querySelector(`[data-row="${row.key}"]`);
      if (!tr) return;
      const buyEl = tr.querySelector('[data-buy]');
      const before = parseInt((buyEl._text || '').replace(/\D/g, ''), 10);
      setRoll(buyEl, fmtRs(row.buy));
      setRoll(tr.querySelector('[data-sell]'), fmtRs(row.sell));

      if (!Number.isNaN(before) && before !== Math.round(row.buy)) {
        const up = row.buy > before;
        tr.classList.toggle('is-up', up);
        tr.classList.toggle('is-down', !up);
        if (!reduced) {
          tr.classList.remove('flash-up', 'flash-down');
          void tr.offsetWidth;
          tr.classList.add(up ? 'flash-up' : 'flash-down');
        }
      }
    });
  });
})();
