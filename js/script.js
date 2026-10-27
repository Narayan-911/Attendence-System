// Attendance Management System - Client-side scripts
document.addEventListener('DOMContentLoaded', function () {
    // Form enhancement: trim text inputs on submit
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const textInputs = form.querySelectorAll('input[type="text"], input[type="email"]');
            textInputs.forEach(input => {
                input.value = input.value.trim();
            });
        });
    });

    // Make alerts dismissible
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.cursor = 'pointer';
        alert.title = 'Click to dismiss';
        alert.addEventListener('click', function () {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(() => alert.remove(), 300);
        });
    });

    // Confirmation for logout
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to log out?')) {
                e.preventDefault();
            }
        });
    });
});