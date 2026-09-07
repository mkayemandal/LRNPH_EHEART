<?php

function eh_search(
    string $id = 'searchInput',
    string $placeholder = 'Search...',
    string $name = 'q'
): string {

    $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safePlaceholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');

    return '
        <div class="eh-search">
            <div class="eh-search-icon">
                <i data-lucide="search"></i>
            </div>

            <input
                type="search"
                class="eh-search-input"
                id="' . $safeId . '"
                name="' . $safeName . '"
                placeholder="' . $safePlaceholder . '"
                autocomplete="off"
                spellcheck="false"
            >

            <button
                type="button"
                class="eh-search-clear"
                id="' . $safeId . 'Clear"
                aria-label="Clear search"
                title="Clear search"
            >
                <i data-lucide="x"></i>
            </button>
        </div>
    ';
}
