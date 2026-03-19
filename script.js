// FAQ accordion
document.querySelectorAll('.faq-q').forEach(btn => {
  btn.addEventListener('click', () => {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});

// Pricing toggle
const toggle = document.getElementById('billing-toggle');
const monthlyLabel = document.getElementById('monthly-label');
const yearlyLabel = document.getElementById('yearly-label');

toggle.addEventListener('change', () => {
  const isYearly = toggle.checked;
  monthlyLabel.classList.toggle('active', !isYearly);
  yearlyLabel.classList.toggle('active', isYearly);

  document.querySelectorAll('.amount').forEach(el => {
    el.textContent = isYearly ? el.dataset.yearly : el.dataset.monthly;
  });
});

// Mobile nav toggle (simple show/hide)
const hamburger = document.getElementById('hamburger');
hamburger.addEventListener('click', () => {
  const links = document.querySelector('.nav-links');
  const actions = document.querySelector('.nav-actions');
  const visible = links.style.display === 'flex';
  links.style.cssText = visible ? '' : 'display:flex;flex-direction:column;position:absolute;top:68px;right:0;left:0;background:#fff;padding:16px 24px;gap:16px;border-bottom:1px solid #e2e8f0;z-index:99';
  actions.style.cssText = visible ? '' : 'display:flex;flex-direction:column;position:absolute;top:calc(68px + 140px);right:0;left:0;background:#fff;padding:0 24px 16px;z-index:99';
});

// Scroll reveal — include accounting cards
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll(
  '.value-card, .feature-item, .pricing-card, .integration-logo, .faq-item, .acc-feature-card, .currency-card, .partner-company-block, .entry-stat'
).forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
  observer.observe(el);
});

// Accounting tabs
document.querySelectorAll('.acc-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.acc-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.acc-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});
