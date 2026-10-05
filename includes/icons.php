<?php

declare(strict_types=1);

function category_icon(string $category): string
{
    $paths = match ($category) {
        'CPU' => '<rect x="12" y="12" width="24" height="24" rx="3"/><rect x="18.5" y="18.5" width="11" height="11" rx="1.5"/>'
               . '<path d="M18 6v6M24 6v6M30 6v6M18 36v6M24 36v6M30 36v6M6 18h6M6 24h6M6 30h6M36 18h6M36 24h6M36 30h6"/>',
        'Cooler' => '<path d="M10 7h28M10 12h28M10 17h28M10 22h28M10 27h28"/><path d="M17 27v9M24 27v9M31 27v9"/>'
               . '<rect x="13" y="36" width="22" height="5" rx="1.5"/>',
        'Motherboard' => '<rect x="7" y="7" width="34" height="34" rx="3"/><rect x="12.5" y="12.5" width="11" height="11" rx="1.5"/>'
               . '<path d="M30 12.5v13M35 12.5v13M12.5 31h23M12.5 36h14"/>',
        'RAM' => '<rect x="5" y="14" width="38" height="15" rx="2"/><path d="M10 19h5v5h-5zM19 19h5v5h-5zM28 19h5v5h-5z"/>'
               . '<path d="M9 29v4M13 29v4M17 29v4M21 29v4M28 29v4M32 29v4M36 29v4M40 29v4"/>',
        'GPU' => '<rect x="4" y="12" width="35" height="21" rx="3"/><circle cx="21.5" cy="22.5" r="6.5"/><circle cx="21.5" cy="22.5" r="1.5"/>'
               . '<path d="M39 15h4.5v15H39M9 33v4M14 33v4M19 33v4M24 33v4"/>',
        'Storage' => '<rect x="9" y="5" width="30" height="38" rx="3"/><circle cx="24" cy="20" r="9"/><circle cx="24" cy="20" r="1.8"/>'
               . '<path d="M31.5 33.5l-5.5-8M14 37.5h5"/>',
        'Case' => '<rect x="12" y="4" width="24" height="37" rx="3"/><circle cx="24" cy="11" r="2.5"/>'
               . '<path d="M18 20h12M18 25h12M18 30h12M15 41v3M33 41v3"/>',
        'PSU' => '<rect x="5" y="10" width="38" height="28" rx="3"/><circle cx="20" cy="24" r="9"/><path d="M21 18.5l-3.5 6.5h5l-2.5 6"/>'
               . '<path d="M35.5 16v5M35.5 27v5"/>',
        default => '<rect x="9" y="9" width="30" height="30" rx="4"/><path d="M9 20h30"/>',
    };
    return icon_svg($paths);
}

function component_icon(array $component): string
{
    if ((string) $component['category_name'] === 'Storage' && (int) ($component['m2_slots'] ?? 0) > 0) {
        return icon_svg('<rect x="9" y="15" width="35" height="18" rx="2"/><path d="M4 19h5M4 24h5M4 29h5"/>'
            . '<rect x="13.5" y="19.5" width="8" height="9" rx="1"/><rect x="25" y="19.5" width="9.5" height="9" rx="1"/>'
            . '<circle cx="39.5" cy="24" r="1.6"/>');
    }
    return category_icon((string) $component['category_name']);
}

function icon_svg(string $paths): string
{
    return '<svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
}
