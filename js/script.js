document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('This is a prototype. Form submission is simulated.');

            if (form.id === 'loginForm') {
                window.location.href = 'dashboard.html';
            }
        });
    });
});