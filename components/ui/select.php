<?php

function eh_select(string $name, string $label, array $options, string $selected = '', string $placeholder = 'Select'): string
{
  $selectedLabel = $options[$selected] ?? $placeholder;

  $items = '<li data-value="" class="' . ($selected === '' ? 'selected' : '') . '">' . htmlspecialchars($placeholder) . '</li>';
  foreach ($options as $value => $optLabel) {
    $isSel = ((string)$value === (string)$selected) ? ' selected' : '';
    $items .= '<li data-value="' . htmlspecialchars((string)$value) . '" class="' . ltrim($isSel) . '">' . htmlspecialchars($optLabel) . '</li>';
  }

  return '
      <div class="eh-field">
        <label class="eh-label">' . htmlspecialchars($label) . '</label>
        <div class="eh-filter eh-dropdown" data-name="' . htmlspecialchars($name) . '">
          <button type="button" class="eh-filter-select" id="' . htmlspecialchars($name) . '-btn">
            <span class="eh-filter-select-label">' . htmlspecialchars($selectedLabel) . '</span>
            <i data-lucide="chevron-down" class="eh-filter-chevron"></i>
          </button>
          <input type="hidden" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($selected) . '">
          <ul class="eh-filter-dropdown">' . $items . '</ul>
        </div>
      </div>
    ';
}
