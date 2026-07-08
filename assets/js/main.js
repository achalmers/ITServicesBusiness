/* ============================================================
   NexaTech Solutions — main.js
   ============================================================ */

(function () {
  'use strict';

  /* ---- Navbar scroll behavior ---- */
  const navbar = document.getElementById('navbar');
  if (navbar) {
    const handleScroll = () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // run on load
  }

  /* ---- Mobile menu toggle ---- */
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('open');
      mobileMenu.classList.toggle('open');
    });
    // Close on link click
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('open');
        mobileMenu.classList.remove('open');
      });
    });
  }

  /* ---- Smooth scroll for anchor links ---- */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        const offset = navbar ? navbar.offsetHeight : 0;
        const top = target.getBoundingClientRect().top + window.scrollY - offset - 20;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });

  /* ---- Intersection Observer for scroll animations ---- */
  const animatedEls = document.querySelectorAll('.animate-on-scroll');
  if (animatedEls.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    animatedEls.forEach(el => observer.observe(el));
  }

  /* ---- Active nav link highlighting ---- */
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a, .mobile-menu a').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  /* ---- Stat counter animation ---- */
  const statEls = document.querySelectorAll('[data-count]');
  if (statEls.length) {
    const countObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          const target = parseFloat(el.dataset.count);
          const suffix = el.dataset.suffix || '';
          const duration = 1800;
          const start = performance.now();
          const animate = (now) => {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            const value = target * ease;
            el.textContent = (Number.isInteger(target) ? Math.floor(value) : value.toFixed(1)) + suffix;
            if (progress < 1) requestAnimationFrame(animate);
          };
          requestAnimationFrame(animate);
          countObserver.unobserve(el);
        }
      });
    }, { threshold: 0.5 });
    statEls.forEach(el => countObserver.observe(el));
  }

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
      const item = question.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      // Close all
      document.querySelectorAll('.faq-item.open').forEach(openItem => {
        openItem.classList.remove('open');
      });
      // Open clicked (if it was closed)
      if (!isOpen) item.classList.add('open');
    });
  });

  /* ---- Contact form AJAX submission ---- */
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = this.querySelector('[type=submit]');
      const msgEl = document.getElementById('contact-form-msg');
      const originalText = btn.textContent;

      btn.textContent = 'Sending…';
      btn.disabled = true;
      if (msgEl) { msgEl.className = ''; msgEl.textContent = ''; }

      const formData = new FormData(this);

      try {
        const tokenRes = await fetch('api/csrf-token.php', { credentials: 'same-origin' });
        const tokenData = await tokenRes.json();
        formData.set('csrf_token', tokenData.csrf_token);

        const res = await fetch('api/contact.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (msgEl) {
          msgEl.className = data.success ? 'alert alert-success' : 'alert alert-error';
          msgEl.textContent = data.success
            ? (data.message || 'Message sent! We\'ll be in touch within 2 business hours.')
            : (data.error || 'Something went wrong. Please try again.');
        }
        if (data.success) this.reset();
      } catch (err) {
        if (msgEl) {
          msgEl.className = 'alert alert-error';
          msgEl.textContent = 'Network error. Please try again or email us directly.';
        }
      } finally {
        btn.textContent = originalText;
        btn.disabled = false;
      }
    });
  }

  /* ---- Registration form AJAX ---- */
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = this.querySelector('[type=submit]');
      const msgEl = document.getElementById('register-msg');
      const originalText = btn.textContent;

      // Client-side password match
      const pw = this.querySelector('[name=password]').value;
      const pw2 = this.querySelector('[name=confirm_password]').value;
      if (pw !== pw2) {
        if (msgEl) { msgEl.className = 'alert alert-error'; msgEl.textContent = 'Passwords do not match.'; }
        return;
      }

      btn.textContent = 'Creating Account…';
      btn.disabled = true;
      if (msgEl) { msgEl.className = ''; msgEl.textContent = ''; }

      const formData = new FormData(this);

      try {
        const res = await fetch('../api/register.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (msgEl) {
          msgEl.className = data.success ? 'alert alert-success' : 'alert alert-error';
          msgEl.textContent = data.success
            ? 'Account created! Please log in.'
            : (data.error || 'Registration failed.');
        }
        if (data.success) {
          this.reset();
          // Switch to login tab
          setTimeout(() => {
            const loginTab = document.querySelector('.auth-tab[data-tab=login]');
            if (loginTab) loginTab.click();
          }, 1500);
        }
      } catch (err) {
        if (msgEl) { msgEl.className = 'alert alert-error'; msgEl.textContent = 'Network error. Please try again.'; }
      } finally {
        btn.textContent = originalText;
        btn.disabled = false;
      }
    });
  }

  /* ---- Ticket submit form AJAX ---- */
  const ticketForm = document.getElementById('ticket-form');
  if (ticketForm) {
    ticketForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = this.querySelector('[type=submit]');
      const msgEl = document.getElementById('ticket-form-msg');
      const originalText = btn.textContent;

      btn.textContent = 'Submitting…';
      btn.disabled = true;
      if (msgEl) { msgEl.className = ''; msgEl.textContent = ''; }

      const formData = new FormData(this);

      try {
        const res = await fetch('submit-ticket.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (msgEl) {
          msgEl.className = data.success ? 'alert alert-success' : 'alert alert-error';
          msgEl.textContent = data.success
            ? `Ticket #${data.ticket_id} submitted successfully!`
            : (data.error || 'Failed to submit ticket.');
        }
        if (data.success) {
          this.reset();
          setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);
        }
      } catch (err) {
        if (msgEl) { msgEl.className = 'alert alert-error'; msgEl.textContent = 'Network error. Please try again.'; }
      } finally {
        btn.textContent = originalText;
        btn.disabled = false;
      }
    });
  }

  /* ---- Portal auth tabs ---- */
  document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
      this.classList.add('active');
      const target = document.getElementById('form-' + this.dataset.tab);
      if (target) target.classList.add('active');
    });
  });

  /* ---- Admin: selects that auto-submit their form on change ---- */
  document.querySelectorAll('select.auto-submit').forEach(select => {
    select.addEventListener('change', () => select.form.submit());
  });

  /* ---- Admin: tickets list "select all" checkbox ---- */
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('input[name="ticket_ids[]"]').forEach(cb => cb.checked = this.checked);
    });
  }

  /* ---- Admin: generic "toggle add-X form" buttons ---- */
  function wireToggleForm(formId, toggleId, cancelId) {
    const form = document.getElementById(formId);
    const toggleBtn = document.getElementById(toggleId);
    const cancelBtn = document.getElementById(cancelId);
    if (form && toggleBtn) toggleBtn.addEventListener('click', () => form.classList.toggle('hidden'));
    if (form && cancelBtn) cancelBtn.addEventListener('click', () => form.classList.add('hidden'));
  }
  wireToggleForm('add-customer-form', 'toggle-add-customer', 'cancel-add-customer');
  wireToggleForm('add-admin-form', 'toggle-add-admin', 'cancel-add-admin');

  /* ---- Admin: print checklist button ---- */
  const printChecklist = document.getElementById('print-checklist');
  if (printChecklist) {
    printChecklist.addEventListener('click', () => window.print());
  }

  /* ---- Admin: dashboard charts (dependency-free canvas rendering) ---- */
  const chartPalette = ['#00d4ff', '#00ff88', '#fbbf24', '#ff6b6b', '#a78bfa', '#f472b6', '#38bdf8', '#facc15'];
  const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

  function drawLineChart(canvas, points) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    const padding = { top: 16, right: 16, bottom: 28, left: 32 };
    const plotW = w - padding.left - padding.right;
    const plotH = h - padding.top - padding.bottom;
    const maxVal = Math.max(1, ...points.map(p => p.value));
    const textColor = cssVar('--text-secondary') || '#94a3b8';
    const lineColor = cssVar('--accent-cyan') || '#00d4ff';

    ctx.clearRect(0, 0, w, h);

    // Gridlines + y-axis labels
    ctx.strokeStyle = 'rgba(148,163,184,0.15)';
    ctx.fillStyle = textColor;
    ctx.font = '10px Inter, sans-serif';
    ctx.textAlign = 'right';
    const ySteps = 4;
    for (let i = 0; i <= ySteps; i++) {
      const y = padding.top + plotH - (plotH * i) / ySteps;
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(w - padding.right, y);
      ctx.stroke();
      ctx.fillText(Math.round((maxVal * i) / ySteps), padding.left - 6, y + 3);
    }

    // Line path
    const stepX = plotW / Math.max(1, points.length - 1);
    const coords = points.map((p, i) => ({
      x: padding.left + stepX * i,
      y: padding.top + plotH - (p.value / maxVal) * plotH,
    }));

    ctx.beginPath();
    coords.forEach((c, i) => (i === 0 ? ctx.moveTo(c.x, c.y) : ctx.lineTo(c.x, c.y)));
    ctx.lineTo(coords[coords.length - 1].x, padding.top + plotH);
    ctx.lineTo(coords[0].x, padding.top + plotH);
    ctx.closePath();
    ctx.fillStyle = 'rgba(0,212,255,0.12)';
    ctx.fill();

    ctx.beginPath();
    coords.forEach((c, i) => (i === 0 ? ctx.moveTo(c.x, c.y) : ctx.lineTo(c.x, c.y)));
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 2;
    ctx.stroke();

    coords.forEach(c => {
      ctx.beginPath();
      ctx.arc(c.x, c.y, 2.5, 0, Math.PI * 2);
      ctx.fillStyle = lineColor;
      ctx.fill();
    });

    // X-axis labels (thin out if too many points for the width)
    ctx.textAlign = 'center';
    ctx.fillStyle = textColor;
    const labelEvery = Math.ceil(points.length / (plotW / 40));
    points.forEach((p, i) => {
      if (i % labelEvery === 0) ctx.fillText(p.label, coords[i].x, h - 8);
    });
  }

  function drawPieChart(canvas, slices) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    const cx = w * 0.32, cy = h / 2, radius = Math.min(cx, cy) - 10;
    const total = slices.reduce((sum, s) => sum + s.value, 0) || 1;

    ctx.clearRect(0, 0, w, h);

    let angle = -Math.PI / 2;
    slices.forEach((s, i) => {
      const sliceAngle = (s.value / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, radius, angle, angle + sliceAngle);
      ctx.closePath();
      ctx.fillStyle = chartPalette[i % chartPalette.length];
      ctx.fill();
      angle += sliceAngle;
    });

    // Legend
    const legendX = w * 0.62;
    const rowH = Math.min(20, h / Math.max(1, slices.length));
    ctx.font = '11px Inter, sans-serif';
    ctx.textAlign = 'left';
    slices.forEach((s, i) => {
      const y = 14 + i * rowH;
      ctx.fillStyle = chartPalette[i % chartPalette.length];
      ctx.fillRect(legendX, y - 8, 10, 10);
      ctx.fillStyle = cssVar('--text-secondary') || '#94a3b8';
      ctx.fillText(`${s.label} (${s.value})`, legendX + 16, y + 1);
    });
  }

  document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
    let data;
    try {
      data = JSON.parse(canvas.dataset.chart);
    } catch (e) {
      return;
    }
    if (!Array.isArray(data) || data.length === 0) return;
    if (canvas.dataset.chartType === 'pie') {
      drawPieChart(canvas, data);
    } else {
      drawLineChart(canvas, data);
    }
  });

  /* ---- Ticket comment reply form (portal) ---- */
  const replyForm = document.getElementById('reply-form');
  if (replyForm) {
    replyForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = this.querySelector('[type=submit]');
      const msgEl = document.getElementById('reply-msg');
      btn.disabled = true;
      btn.textContent = 'Sending…';

      const formData = new FormData(this);
      try {
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        // For simplicity, reload page on success to show new comment
        if (res.ok) {
          window.location.reload();
        }
      } catch (err) {
        if (msgEl) { msgEl.className = 'alert alert-error'; msgEl.textContent = 'Failed to send reply.'; }
        btn.disabled = false;
        btn.textContent = 'Send Reply';
      }
    });
  }

})();
