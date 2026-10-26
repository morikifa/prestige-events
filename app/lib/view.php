<?php

function render(string $template, array $data = []): void
{
    $data['currentUser'] = current_user();
    $data['flashes'] = flash_pull();
    $data['theme'] = $_SESSION['theme'] ?? 'light';
    $data['navActive'] = $data['navActive'] ?? '';

    extract($data, EXTR_SKIP);

    ob_start();
    include PRESTIGE_APP . '/views/' . $template . '.php';
    $content = ob_get_clean();

    include PRESTIGE_APP . '/views/layout.php';
}

function partial(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    include PRESTIGE_APP . '/views/partials/' . $template . '.php';
}

function nav_link(string $route, string $label, string $active, string $key, array $params = []): string
{
    $class = $active === $key ? 'is-active' : '';
    return '<a class="' . $class . '" href="' . e(url($route, $params)) . '">' . e($label) . '</a>';
}
