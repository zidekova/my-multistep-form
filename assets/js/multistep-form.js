document.addEventListener('DOMContentLoaded', function () {

  const container = document.querySelector('.mmf-container');
  if (!container) return;

  const steps = container.querySelectorAll('.mmf-step');
  const btnPrev = container.querySelector('.mmf-prev');
  const btnNext = container.querySelector('.mmf-next');
  const btnSubmit = container.querySelector('.mmf-submit');

  const progressFill = container.querySelector('.mmf-progress-fill');
  const progressSteps = container.querySelectorAll('.mmf-progress-step');

  let current = 0;
  const total = steps.length;

  function updateUI() {

    steps.forEach((step, index) => {
      step.classList.toggle('is-active', index === current);
    });

    // SAFE progress bar
    if (progressFill) {
      const percent = (current / (total - 1)) * 100;
      progressFill.style.width = percent + '%';
    }

    // SAFE progress dots
    if (progressSteps.length) {
      progressSteps.forEach((dot, index) => {
        dot.classList.toggle('is-active', index === current);
        dot.classList.toggle('is-complete', index < current);
      });
    }

    if (btnPrev) btnPrev.disabled = current === 0;
    if (btnNext) btnNext.style.display = current === total - 1 ? 'none' : 'inline-block';
    if (btnSubmit) btnSubmit.style.display = current === total - 1 ? 'inline-block' : 'none';
    // Ak sme v kroku 6 (index 5), vykreslíme porovnanie cien
    if (current === 5) {
      try { renderPricingComparison(); } catch (e) { /* ignore */ }
    }
  }

  if (btnNext) {
    btnNext.addEventListener('click', function () {

      // VALIDÁCIA pre krok 1 (výber typu akcie)
      if (current === 0) {
        const selected = container.querySelector('input[name="event_type"]:checked');
        if (!selected) {
          alert('Prosím vyberte typ akcie.');
          return;
        }
      }

      // VALIDÁCIA pre krok 2 (výber doby trvania)
      if (current === 1) {
        const selected = container.querySelector('input[name="duration"]:checked');
        if (!selected) {
          alert('Prosím vyberte dobu trvania.');
          return;
        }
      }

      // VALIDÁCIA pre krok 3 (výber balíčka)
      if (current === 2) {
        const selected = container.querySelector('input[name="package"]:checked');
        if (!selected) {
          alert('Prosím vyberte balíček.');
          return;
        }
      }

      // VALIDÁCIA pre krok 5 (výber regiónu)
      if (current === 4) {
        const selected = container.querySelector('input[name="region"]:checked, input[name="region"]:not([value=""])');
        const hiddenRegion = container.querySelector('input[name="region"]');
        if (!hiddenRegion || !hiddenRegion.value) {
          alert('Prosím vyberte kraj.');
          return;
        }
      }

      // VALIDÁCIA pre krok 7 (kontaktné údaje)
      if (current === 6) {
        const name = container.querySelector('input[name="contact_name"]');
        const email = container.querySelector('input[name="contact_email"]');
        const phone = container.querySelector('input[name="contact_phone"]');
        const eventDate = container.querySelector('input[name="event_date"]');
        
        if (!name || !name.value.trim()) {
          alert('Prosím vyplňte vaše meno.');
          name?.focus();
          return;
        }
        
        if (!email || !email.value.trim()) {
          alert('Prosím vyplňte váš email.');
          email?.focus();
          return;
        }
        
        // Základná validácia emailu
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
          alert('Prosím zadajte platný email.');
          email?.focus();
          return;
        }
        
        if (!phone || !phone.value.trim()) {
          alert('Prosím vyplňte vaše telefónne číslo.');
          phone?.focus();
          return;
        }
        
        if (!eventDate || !eventDate.value) {
          alert('Prosím vyberte dátum akcie.');
          eventDate?.focus();
          return;
        }
      }

      if (current < total - 1) {
        current++;
        updateUI();
        // smooth scroll the form container into view (top)
        try { container.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* ignore */ }
      }
    });
  }

  if (btnPrev) {
    btnPrev.addEventListener('click', function () {
      if (current > 0) {
        current--;
        updateUI();
        // smooth scroll the form container into view (top)
        try { container.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* ignore */ }
      }
    });
  }

  // REGION MAP INTERAKCIA (KROK 5)
  const regionPaths = container.querySelectorAll('.mmf-region-path');
  const selectedRegionDisplay = container.querySelector('#selected-region');
  const regionHidden = container.querySelector('input[name="region"]');
  
  // Vytvárame tooltip element
  const tooltip = document.createElement('div');
  tooltip.className = 'mmf-region-tooltip';
  document.body.appendChild(tooltip);

  regionPaths.forEach(path => {
    path.addEventListener('click', function () {
      const region = this.getAttribute('data-region');
      
      // Odstránime selected triedu z všetkých
      regionPaths.forEach(p => p.classList.remove('selected'));
      
      // Pridáme selected triedu do kliknutého
      this.classList.add('selected');
      
      // Zase vyber text a hidden input
      if (selectedRegionDisplay) {
        selectedRegionDisplay.textContent = region;
      }
      if (regionHidden) {
        regionHidden.value = region;
      }
    });
    
    // HOVER - zobrazí tooltip
    path.addEventListener('mouseenter', function () {
      const region = this.getAttribute('data-region');
      tooltip.textContent = region;
      tooltip.classList.add('visible');
    });
    
    // MOUSE MOVE - pozične tooltip
    path.addEventListener('mousemove', function (e) {
      const offsetX = 15;
      const offsetY = -10;
      tooltip.style.left = (e.clientX + offsetX) + 'px';
      tooltip.style.top = (e.clientY + offsetY) + 'px';
    });
    
    // MOUSE LEAVE - skryje tooltip
    path.addEventListener('mouseleave', function () {
      tooltip.classList.remove('visible');
    });
  });

  updateUI();
  // --- Pricing: read config (from template) and helper functions ---
  const PRICING = window.MMF_PRICING || { base: 650, eventType: {}, duration: {}, package: {}, extensions: {}, region: {}, includedExtensions: {} };

  function safeNumber(v) {
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function getSelectedValue(name) {
    const el = container.querySelector(`input[name="${name}"]:checked`);
    return el ? el.value : '';
  }

  function getSelectedExtensions() {
    const els = Array.from(container.querySelectorAll('input[name="extensions[]"]:checked'));
    return els.map(e => e.value);
  }

  function getSelectedRegion() {
    const hidden = container.querySelector('input[name="region"]');
    return hidden && hidden.value ? hidden.value : '';
  }

  function priceForRegion(region) {
    if (!region) return 0;
    return PRICING.region && PRICING.region[region] ? safeNumber(PRICING.region[region]) : 0;
  }

  function calculatePriceForPackage(packageKey) {
    let total = 0;
    total += safeNumber(PRICING.base);

    const eventType = getSelectedValue('event_type');
    total += PRICING.eventType && PRICING.eventType[eventType] ? safeNumber(PRICING.eventType[eventType]) : 0;

    const duration = getSelectedValue('duration');
    total += PRICING.duration && PRICING.duration[duration] ? safeNumber(PRICING.duration[duration]) : 0;

    // package base price
    total += PRICING.package && PRICING.package[packageKey] ? safeNumber(PRICING.package[packageKey]) : 0;

    // extensions: if an extension is included in the package, skip its price
    const selectedExt = getSelectedExtensions();
    const included = (PRICING.includedExtensions && PRICING.includedExtensions[packageKey]) || [];
    selectedExt.forEach(ext => {
      if (included.indexOf(ext) === -1) {
        total += PRICING.extensions && PRICING.extensions[ext] ? safeNumber(PRICING.extensions[ext]) : 0;
      }
    });

    // region
    const region = getSelectedRegion();
    total += priceForRegion(region);

    return Math.round(total);
  }

  function renderPricingComparison() {
    const container6 = container.querySelector('.mmf-step[data-step="6"]');
    if (!container6) return;

    const resultsEl = container6.querySelector('#mmf-pricing-results');
    if (!resultsEl) return;

    const packages = ['cista-zabava', 'atmosfera', 'wow-efekt'];
    const packageTitles = {
      'cista-zabava': 'Čistá zábava',
      'atmosfera': 'Atmosféra, ktorú si hostia zapamätajú',
      'wow-efekt': 'Eventový WOW efekt'
    };
    const packageBenefits = {
      'cista-zabava': 'DJ, moderovanie, kvalitné ozvučenie a základná svetelná atmosféra.',
      'atmosfera': 'Rozšírené osvetlenie, nasvietenie sály a práca s atmosférou večera.',
      'wow-efekt': 'Plnohodnotná svetelná show s profesionálnym osvetľovačom a výrazným WOW momentom v cene.'
    };

    const results = packages.map(pk => ({ key: pk, title: packageTitles[pk] || pk, price: calculatePriceForPackage(pk) }));

    // find cheapest
    const minPrice = Math.min(...results.map(r => r.price));

    const selectedPkg = getSelectedValue('package');

    let html = '<div class="mmf-pricing-cards">';
    results.forEach(r => {
      const cheapestClass = (r.price === minPrice) ? ' mmf-cheapest' : '';
      const selectedClass = (selectedPkg && selectedPkg === r.key) ? ' mmf-selected' : '';
      // icon map similar to step 3
      const icons = {
        'cista-zabava': 'fas fa-glass-cheers',
        'atmosfera': 'fas fa-star',
        'wow-efekt': 'fas fa-crown'
      };
      const iconClass = icons[r.key] || 'fas fa-music';
      html += `\n  <div class="mmf-pricing-card${cheapestClass}${selectedClass}" data-pkg="${r.key}">`;
      html += `\n    <div class="mmf-package-card">`;
      html += `\n      <div class="mmf-package-box${selectedClass ? ' mmf-selected' : ''}">`;
      html += `\n        <i class="mmf-package-icon ${iconClass}"></i>`;
      html += `\n        <div class="mmf-package-title">${r.title}</div>`;
      html += `\n        <div class="mmf-package-desc">${packageBenefits[r.key] || ''}</div>`;
      html += `\n        <div class="mmf-pricing-amount">${r.price} €</div>`;
      html += `\n      </div>`;
      html += `\n    </div>`;
      html += '\n  </div>';
    });
    html += '\n</div>';

    // no per-card breakdown; show a single shared note (element exists in template)

    resultsEl.innerHTML = html;

    // visually keep radio state in sync: when a package radio is checked, highlight card
    const cards = resultsEl.querySelectorAll('.mmf-pricing-card');
    cards.forEach(card => {
      const pk = card.getAttribute('data-pkg');
      const radio = container.querySelector(`input[name="package"][value="${pk}"]`);
      if (radio && radio.checked) {
        card.classList.add('mmf-selected');
      } else {
        card.classList.remove('mmf-selected');
      }
      // clicking the whole card also selects package
      card.addEventListener('click', function (e) {
        // avoid double-trigger when clicking the inner button
        if (e.target && e.target.classList && e.target.classList.contains('mmf-select-package')) return;
        const pkg = this.getAttribute('data-pkg');
        const pkgInput = container.querySelector(`input[name="package"][value="${pkg}"]`);
        if (pkgInput) {
          pkgInput.checked = true;
          pkgInput.dispatchEvent(new Event('change', { bubbles: true }));
          renderPricingComparison();
        }
      });
    });

    // attach click handlers for "Vybrať tento balík" buttons
    resultsEl.querySelectorAll('.mmf-select-package').forEach(btn => {
      btn.addEventListener('click', function () {
        const pkg = this.getAttribute('data-pkg');
        const pkgInput = container.querySelector(`input[name="package"][value="${pkg}"]`);
        if (pkgInput) {
          pkgInput.checked = true;
          // trigger change to update breakdown
          pkgInput.dispatchEvent(new Event('change', { bubbles: true }));
          renderPricingComparison();
        }
      });
    });
  }

  // Recompute pricing when user changes relevant inputs
  ['event_type', 'duration', 'package', 'extensions[]'].forEach(name => {
    container.querySelectorAll(`input[name="${name}"]`).forEach(inp => {
      inp.addEventListener('change', function () {
        // only update if we are in the comparison step
        const active = container.querySelector('.mmf-step.is-active');
        if (active && active.getAttribute('data-step') === '6') renderPricingComparison();
      });
    });
  });

  // Also re-render when region hidden input changes
  if (regionHidden) {
    regionHidden.addEventListener('change', function () {
      const active = container.querySelector('.mmf-step.is-active');
      if (active && active.getAttribute('data-step') === '6') renderPricingComparison();
    });
  }
  
  // Ensure final_price hidden input is set before submit so server can send correct total
  const formEl = container.querySelector('.mmf-form');
  if (formEl) {
    formEl.addEventListener('submit', function (e) {
      // set final price for selected package
      const selectedPkg = getSelectedValue('package');
      if (selectedPkg) {
        const price = calculatePriceForPackage(selectedPkg);
        const finalInput = formEl.querySelector('input[name="final_price"]');
        if (finalInput) finalInput.value = price;
      }
    });
  }
});

