<?php

function eh_filter(
    string $id,
    string $placeholder,
    array $options = [],
    string $selected = '',
    string $name = ''
): string {

    $name = $name ?: $id;

    $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safePlaceholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $selectedLabel = $options[$selected] ?? $placeholder;

    $items = '<li data-value="" class="' . ($selected === '' ? 'selected' : '') . '">' . $safePlaceholder . '</li>';

    foreach ($options as $value => $label) {
        $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        $isSelected = ((string) $value === (string) $selected) ? ' selected' : '';
        $items .= '<li data-value="' . $safeValue . '" class="' . ltrim($isSelected) . '">' . $safeLabel . '</li>';
    }

    return '
        <div class="eh-filter eh-dropdown" data-name="' . $safeName . '">
            <button type="button" class="eh-filter-select" id="' . $safeId . '-btn">
                <span class="eh-filter-select-label">' . htmlspecialchars($selectedLabel, ENT_QUOTES, 'UTF-8') . '</span>
                <i data-lucide="chevron-down" class="eh-filter-chevron"></i>
            </button>

            <input type="hidden" id="' . $safeId . '" name="' . $safeName . '" value="' . htmlspecialchars($selected, ENT_QUOTES, "UTF-8") . '">

            <ul class="eh-filter-dropdown">' . $items . '</ul>
        </div>
    ';
}
