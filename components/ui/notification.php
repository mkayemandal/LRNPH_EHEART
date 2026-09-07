<?php

function eh_notification_item(array $n): string
{
    $unread = !$n['is_read'] ? 'style="font-weight:600;"' : '';
    return "<div class=\"eh-notification-item\" {$unread}>
        <div class=\"title\">" . htmlspecialchars($n['title']) . "</div>
        <div class=\"message\">" . htmlspecialchars($n['message']) . "</div>
        <div class=\"time\">" . htmlspecialchars($n['created_at']) . "</div>
    </div>";
}
