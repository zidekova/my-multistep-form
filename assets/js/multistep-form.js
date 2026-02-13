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
      }
    });
  }

  if (btnPrev) {
    btnPrev.addEventListener('click', function () {
      if (current > 0) {
        current--;
        updateUI();
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
});

