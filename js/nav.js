/* nav.js — inject shared navbar and footer */
(function () {
  const navHTML = `
  <nav class="navbar">
    <div class="container">
      <a class="logo" href="../index.html">
        <div class="logo-icon">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/>
          </svg>
        </div>
        <div class="logo-text">
          <strong>CDRC Relief Tracker</strong>
          <span>Citizens' Disaster Response Center</span>
        </div>
      </a>
      <div class="nav-links" id="navLinks">
        <a href="../index.html">Home</a>
        <a href="../pages/about.html">About</a>
        <a href="../pages/features.html">Features</a>
        <a href="../pages/contact.html">Contact</a>
        <a href="../pages/login.html" class="btn btn-primary nav-cta">System Login</a>
      </div>
      <div class="hamburger" id="hamburger">
        <span></span><span></span><span></span>
      </div>
    </div>
  </nav>`;

  const footerHTML = `
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="logo" href="../index.html">
            <div class="logo-icon">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/>
              </svg>
            </div>
            <div class="logo-text">
              <strong>CDRC Relief Tracker</strong>
              <span>Citizens' Disaster Response Center</span>
            </div>
          </a>
          <p style="margin-top:16px;">A web-based disaster relief distribution tracking system for efficient community-based disaster response across the Philippines.</p>
        </div>
        <div class="footer-col">
          <h4>Navigation</h4>
          <a href="../index.html">Home</a>
          <a href="../pages/about.html">About CDRC</a>
          <a href="../pages/features.html">Features</a>
          <a href="../pages/contact.html">Contact</a>
        </div>
        <div class="footer-col">
          <h4>System</h4>
          <a href="../pages/login.html">Login</a>
          <a href="../pages/dashboard.html">Dashboard</a>
          <a href="../pages/records.html">Records</a>
          <a href="../pages/reports.html">Reports</a>
        </div>
        <div class="footer-col">
          <h4>Contact</h4>
          <a href="#">cdrc@cdrc.org.ph</a>
          <a href="#">+63 2 8921 1765</a>
          <a href="#">Quezon City, Philippines</a>
          <a href="#">facebook.com/CDRCPhilippines</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span style="color:var(--gray-500);">© 2025 Citizens' Disaster Response Center. All rights reserved.</span>
        <span>ITS131P — Group 9</span>
      </div>
    </div>
  </footer>`;

  document.addEventListener('DOMContentLoaded', () => {
    const navPlaceholder = document.getElementById('nav-placeholder');
    const footerPlaceholder = document.getElementById('footer-placeholder');
    if (navPlaceholder) navPlaceholder.innerHTML = navHTML;
    if (footerPlaceholder) footerPlaceholder.innerHTML = footerHTML;
  });
})();
