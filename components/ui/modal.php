<?php

function eh_modal(string $id, string $title, string $bodyHtml, string $footerHtml = ''): string
{
    return "<div id=\"{$id}\" class=\"eh-modal-overlay\" style=\"display:none;\">
        <div class=\"eh-modal\">
            <div class=\"eh-modal-title\">{$title}</div>
            <div class=\"eh-modal-body\">{$bodyHtml}</div>
            <div class=\"eh-modal-actions\">{$footerHtml}</div>
        </div>
    </div>";
}
