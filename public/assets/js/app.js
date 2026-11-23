/* =============================================================================
   TASTE ALCHEMY · interfaz
   Sin dependencias. Mejora progresiva: si el fetch falla, el formulario sigue
   funcionando con el envío normal al servidor.
   ========================================================================== */
(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Navegación móvil ---------- */
  const navToggle = document.querySelector('[data-nav-toggle]');
  const nav = document.getElementById('site-nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- Confirmaciones ---------- */
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-confirm]')) {
      const message = form.getAttribute('data-confirm') || '¿Seguro?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    }
  });

  /* ---------- Acordeones del constructor ---------- */
  document.querySelectorAll('[data-accordion]').forEach((button) => {
    const target = document.getElementById(button.getAttribute('aria-controls') || '');
    if (!target) return;
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      target.hidden = !open;
    });
  });

  /* ---------- Pestañas ---------- */
  document.querySelectorAll('[data-tabs]').forEach((group) => {    const tabs = Array.from(group.querySelectorAll('[role="tab"]'));
    const panels = Array.from(group.querySelectorAll('[role="tabpanel"]'));
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activate(index));
      tab.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          e.preventDefault();
          const next = (index + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
          tabs[next].focus();
          activate(next);
        }
      });
    });
    function activate(index) {
      tabs.forEach((t, i) => {
        t.setAttribute('aria-selected', i === index ? 'true' : 'false');
        t.tabIndex = i === index ? 0 : -1;
      });
      panels.forEach((p, i) => { p.hidden = i !== index; });
    }
  });

  /* ---------- Constructor ---------- */
  const form = document.getElementById('builder-form');
  const result = document.getElementById('builder-result');
  if (!form || !result) {
    return;
  }

  const debounce = (fn, wait) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  };

  const esc = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  const nf = (value, decimals) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';
    return number.toLocaleString('es-ES', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  };

  function selectedValue(name) {
    const checked = form.querySelector(`input[name="${name}"]:checked`);
    return checked ? checked.value : '';
  }

  function syncChoices() {
    form.querySelectorAll('.choice').forEach((choice) => {
      const input = choice.querySelector('input');
      if (!input) return;
      choice.classList.toggle('selected', input.checked);
      choice.setAttribute('aria-pressed', input.checked ? 'true' : 'false');
    });
    form.querySelectorAll('.radio-pill').forEach((pill) => {
      const input = pill.querySelector('input');
      if (!input) return;
      pill.setAttribute('aria-pressed', input.checked ? 'true' : 'false');
    });
  }

  function syncMode() {
    const mode = selectedValue('mode') || 'ingredientes';
    form.querySelectorAll('[data-mode-panel]').forEach((panel) => {
      panel.hidden = panel.getAttribute('data-mode-panel') !== mode;
    });
  }

  function syncBlend() {
    const blend = form.querySelector('input[name="blend_enabled"]');
    const pct = form.querySelector('[data-blend-pct]');
    if (pct) pct.hidden = !(blend && blend.checked);
  }

  function collect() {
    const data = {};
    new FormData(form).forEach((value, key) => {
      if (key === '_token') return;
      if (key.endsWith('[]')) {
        const clean = key.slice(0, -2);
        data[clean] = data[clean] || [];
        data[clean].push(value);
      } else {
        data[key] = value;
      }
    });
    data.blend_enabled = !!(form.querySelector('input[name="blend_enabled"]') || {}).checked;
    data.split = !!(form.querySelector('input[name="split"]') || {}).checked;
    return data;
  }

  function renderJars(jars, unit) {
    if (!Array.isArray(jars) || jars.length === 0) {
      return '<span class="muted small">Sin frascos.</span>';
    }
    return jars.map((jar) => {
      const pct = Math.max(4, Math.min(100, Number(jar.fill_pct || 0)));
      const label = unit === 'ml' ? nf(jar.ml, 0) + ' ml' : nf(jar.total_g, 0) + ' g';
      return `<span class="jar" title="${esc(label)}"><i style="height:${pct}%"></i><span>${esc(nf(pct, 0))}%</span></span>`;
    }).join('');
  }

  function renderTimeline(timeline) {
    if (!timeline || !Array.isArray(timeline.items)) return '';
    return timeline.items.map((item) => `
      <div class="event">
        <div class="when">${esc(item.label)} · día ${esc(item.at_day)}</div>
        <div class="what">${esc(item.title)}</div>
        <div class="why">${esc(item.body)}</div>
      </div>`).join('');
  }

  function renderResult(data) {
    const freeze = data.freeze || {};
    const volumes = data.volumes || {};
    const warnings = Array.isArray(data.warnings) ? data.warnings : [];
    const checks = [
      ['ABV', nf(data.abv_pct, 1) + ' %'],
      ['Brix', nf(data.brix, 1) + ' °Bx'],
      ['Azúcar', nf(data.sugar_gl, 0) + ' g/L'],
      ['Volumen final', nf(volumes.final_ml, 0) + ' ml'],
      ['Punto de congelación', nf(freeze.freezing_point_c, 1) + ' °C'],
      ['Servicio', nf(freeze.serving_temperature_c, 0) + ' °C'],
      ['Botellas de 700 ml', nf(data.bottles_700_ml, 2)],
      ['Dulzor', esc(data.sweetness || '—')],
    ];

    result.innerHTML = `
      <div class="result-hero">
        <div class="caption">Graduación estimada</div>
        <div class="abv">${nf(data.abv_pct, 1)}<small> % vol</small></div>
        <div class="muted small">${esc((data.preset && data.preset.label) || '')} · ${esc((data.extraction && data.extraction.label) || '')}</div>
      </div>
      <dl class="kv">
        ${checks.map(([k, v]) => `<dt>${k}</dt><dd>${v}</dd>`).join('')}
      </dl>
      <div class="divider"></div>
      <div class="small gold">Frascos de fruta</div>
      <div class="jars">${renderJars((data.jars || {}).fruit, 'g')}</div>
      <div class="small gold mt-2">Frascos de alcohol</div>
      <div class="jars">${renderJars((data.jars || {}).vodka, 'ml')}</div>
      ${warnings.length ? `<div class="notice mt-2"><b>Atención.</b><ul style="margin:8px 0 0;padding-left:18px">${warnings.map((w) => `<li>${esc(w)}</li>`).join('')}</ul></div>` : ''}
      <div class="divider"></div>
      <div class="small gold">Cronograma</div>
      <div class="timeline">${renderTimeline(data.timeline)}</div>
    `;

    // Actualiza también los elementos marcados fuera del panel.
    document.querySelectorAll('[data-output]').forEach((node) => {
      const key = node.getAttribute('data-output');
      const value = key.split('.').reduce((acc, part) => (acc == null ? acc : acc[part]), data);
      if (value !== undefined && value !== null) {
        node.textContent = typeof value === 'number' ? nf(value, 1) : String(value);
      }
    });
  }

  let controller = null;
  const calculate = debounce(async () => {
    if (controller) controller.abort();
    controller = new AbortController();
    result.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch('/api/v1/calculator', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(collect()),
        signal: controller.signal,
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const payload = await response.json();
      renderResult(payload.data || payload);
    } catch (error) {
      if (error.name !== 'AbortError') {
        result.removeAttribute('aria-busy');
      }
    } finally {
      result.removeAttribute('aria-busy');
    }
  }, 260);

  form.addEventListener('change', () => {
    syncChoices();
    syncMode();
    syncBlend();
    calculate();
  });
  form.addEventListener('input', calculate);
  syncChoices();
  syncMode();
  syncBlend();

  if (!reduceMotion) {
    document.querySelectorAll('[data-reveal]').forEach((node) => {
      node.style.opacity = '0';
      requestAnimationFrame(() => {
        node.style.transition = 'opacity 0.4s ease';
        node.style.opacity = '1';
      });
    });
  }
})();
