<?php

function eh_pagination(int $currentPage, int $totalPages): string
{
    $html = '<div class="eh-pagination">';
    for ($i = 1; $i <= max($totalPages, 1); $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html .= "<button class=\"{$active}\" data-page=\"{$i}\">{$i}</button>";
    }
    $html .= '</div>';
    return $html;
}
