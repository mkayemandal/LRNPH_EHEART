<?php

function eh_date_range(
    string $fromId = 'dateFrom',
    string $toId = 'dateTo',
    string $fromName = 'date_from',
    string $toName = 'date_to'
): string {

    $safeFromId = htmlspecialchars($fromId, ENT_QUOTES, 'UTF-8');
    $safeToId = htmlspecialchars($toId, ENT_QUOTES, 'UTF-8');
    $safeFromName = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
    $safeToName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');

    return '
        <div class="eh-date-range" id="' . $safeFromId . 'Range" data-from-id="' . $safeFromId . '" data-to-id="' . $safeToId . '">

            <div class="eh-date-field">
                <div class="eh-date-input-wrap">
                    <input type="text" class="eh-date-input" id="' . $safeFromId . 'Display" placeholder="mm/dd/yyyy - mm/dd/yyyy" readonly autocomplete="off">
                    <i data-lucide="calendar" class="eh-date-input-icon"></i>
                </div>
            </div>

            <input type="hidden" id="' . $safeFromId . '" name="' . $safeFromName . '">
            <input type="hidden" id="' . $safeToId . '" name="' . $safeToName . '">

            <div class="eh-calendar-popup" id="' . $safeFromId . 'Popup"></div>

        </div>
    ';
}
