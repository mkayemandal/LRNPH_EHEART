<?php
function eh_line_chart(string $canvasId, string $height = '260px'): string {
    return "<canvas id=\"{$canvasId}\" style=\"max-height: {$height};\"></canvas>";
}
