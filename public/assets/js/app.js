document.addEventListener("click", function (event) {
    var target = event.target.closest("[data-confirm]");
    if (!target) {
        return;
    }
    if (!window.confirm(target.getAttribute("data-confirm"))) {
        event.preventDefault();
    }
});

document.querySelectorAll("[data-autosubmit]").forEach(function (element) {
    element.addEventListener("change", function () {
        if (element.form) {
            element.form.submit();
        }
    });
});

document.querySelectorAll("[data-print]").forEach(function (button) {
    button.addEventListener("click", function () {
        window.print();
    });
});
