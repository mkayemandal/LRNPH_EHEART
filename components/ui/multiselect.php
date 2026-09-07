<?php
function eh_multiselect_core_values(array $options, string $fieldName = 'core_values'): string
{
    $itemsHtml = '';
    foreach ($options as $opt) {
        $id = htmlspecialchars((string) $opt['core_value_id'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($opt['core_value_name'], ENT_QUOTES, 'UTF-8');
        $itemsHtml .= "<li data-value=\"{$id}\" data-label=\"{$name}\">{$name}</li>";
    }

    return "<div class=\"eh-field\">
        <label class=\"eh-label\">Value Demonstrated</label>
        <div class=\"eh-filter eh-cv-picker\" id=\"cvPicker\">
            <button type=\"button\" class=\"eh-filter-select\" id=\"cv-select-btn\">
                <span class=\"eh-filter-select-label\" id=\"cv-select-label\">Select at least one</span>
                <i data-lucide=\"chevron-down\" class=\"eh-filter-chevron\"></i>
            </button>
            <ul class=\"eh-filter-dropdown\" id=\"cv-select-list\">
                {$itemsHtml}
            </ul>
        </div>
        <div class=\"eh-multiselect\" id=\"cv-chips\"></div>
        <input type=\"hidden\" name=\"{$fieldName}\" id=\"{$fieldName}\" value=\"[]\">
    </div>";
}
