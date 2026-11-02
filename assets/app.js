document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('menuBtn');
    var nav = document.getElementById('mainNav');
    if (btn && nav) {
        btn.addEventListener('click', function () {
            nav.classList.toggle('open');
        });
    }
    var forms = document.querySelectorAll('form[data-confirm]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
    var regForm = document.getElementById('registerForm');
    if (regForm) {
        regForm.addEventListener('submit', function (event) {
            var p1 = document.getElementById('password').value;
            var p2 = document.getElementById('password2').value;
            if (p1.length < 4) {
                alert('Пароль должен быть не короче 4 символов.');
                event.preventDefault();
                return;
            }
            if (p1 !== p2) {
                alert('Пароли не совпадают.');
                event.preventDefault();
            }
        });
    }
    var eventForm = document.getElementById('eventForm');
    if (eventForm) {
        eventForm.addEventListener('submit', function (event) {
            var starts = document.getElementById('starts_at').value;
            var ends = document.getElementById('ends_at').value;
            if (starts && ends && ends < starts) {
                alert('Дата окончания не может быть раньше даты начала.');
                event.preventDefault();
            }
        });
    }
});
