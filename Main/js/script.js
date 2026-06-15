document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Submission successful! (This is a placeholder action.)');

            if (form.id === 'loginForm') {
                window.location.href = 'dashboard.html';
            }
        });
    });
});