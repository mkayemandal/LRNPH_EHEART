<?php

function eh_loading(string $message = 'Loading...'): string
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div class="eh-loading-component" role="status" aria-live="polite">
    <span class="eh-loading-spinner" aria-hidden="true"></span>
    <span>{$safeMessage}</span>
</div>
HTML;
}
