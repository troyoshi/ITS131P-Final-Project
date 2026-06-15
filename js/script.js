// js/script.js
document.addEventListener('DOMContentLoaded', () => {
    // Prevent default form submission for the prototype
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('This is a prototype. Form submission is simulated.');

            // If it's the login form, redirect to dashboard
            if (form.id === 'loginForm') {
                window.location.href = 'dashboard.html';
            }
        });
    });
});