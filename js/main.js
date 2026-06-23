/* =============================================
   CDRC Relief Tracker — Main JS
   ============================================= */

// ---- Mobile Nav ----
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');

if (hamburger && navLinks) {
  hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    hamburger.classList.toggle('open');
  });
  navLinks.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      navLinks.classList.remove('open');
      hamburger.classList.remove('open');
    });
  });
}

// ---- Active nav link ----
function setActiveNav() {
  const path = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(a => {
    const href = a.getAttribute('href');
    if (href === path) a.classList.add('active');
    else a.classList.remove('active');
  });
}
setActiveNav();

// ---- Scroll animations ----
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

// ---- Contact form ----
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = contactForm.querySelector('button[type="submit"]');
    const original = btn.textContent;
    btn.textContent = '✓ Message Sent!';
    btn.style.background = 'var(--teal)';
    btn.disabled = true;
    setTimeout(() => {
      btn.textContent = original;
      btn.style.background = '';
      btn.disabled = false;
      contactForm.reset();
    }, 3000);
  });
}

// ---- Counter animation ----
function animateCounter(el) {
  const target = parseInt(el.getAttribute('data-target'));
  const duration = 1200;
  const step = target / (duration / 16);
  let current = 0;
  const timer = setInterval(() => {
    current += step;
    if (current >= target) { current = target; clearInterval(timer); }
    el.textContent = Math.floor(current).toLocaleString();
  }, 16);
}

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting && !e.target.classList.contains('counted')) {
      e.target.classList.add('counted');
      animateCounter(e.target);
    }
  });
}, { threshold: 0.5 });

document.querySelectorAll('.counter').forEach(el => counterObserver.observe(el));

// ---- Login Role Switcher ----
const roleBtns = document.querySelectorAll('.role-btn');
roleBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    roleBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});

// ---- Dashboard tab nav ----
const dashLinks = document.querySelectorAll('[data-dash-page]');
const dashPages = document.querySelectorAll('[data-page]');

function showDashPage(name) {
  dashPages.forEach(p => {
    p.style.display = p.getAttribute('data-page') === name ? 'block' : 'none';
  });
  dashLinks.forEach(l => {
    if (l.getAttribute('data-dash-page') === name) l.classList.add('active');
    else l.classList.remove('active');
  });
}

dashLinks.forEach(l => {
  l.addEventListener('click', (e) => {
    e.preventDefault();
    showDashPage(l.getAttribute('data-dash-page'));
  });
});

if (dashPages.length > 0) showDashPage('dashboard');

// ---- Mini bar chart fill animation ----
window.addEventListener('load', () => {
  document.querySelectorAll('.bar').forEach(bar => {
    const fill = parseFloat(bar.style.getPropertyValue('--fill') || bar.getAttribute('data-fill') || 0);
    bar.style.setProperty('--fill', fill);
  });
});

// ---- Smooth scroll for anchor links ----
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

// ---- Navbar shrink on scroll ----
window.addEventListener('scroll', () => {
  const nav = document.querySelector('.navbar');
  if (nav) {
    if (window.scrollY > 40) nav.style.background = 'rgba(13,34,64,0.98)';
    else nav.style.background = 'var(--navy)';
  }
});

// ---- CSS chart animation on load ----
document.querySelectorAll('.cc-bar').forEach(bar => {
  const h = bar.getAttribute('data-height') || '60px';
  bar.style.height = '0';
  setTimeout(() => {
    bar.style.transition = 'height 0.8s ease';
    bar.style.height = h;
  }, 200);
});
