<?php

class DateHelper
{
    public static function addDays(string $date, int $days): string
    {
        $dt = new DateTime($date);
        $dt->modify("+{$days} days");
        return $dt->format('Y-m-d');
    }

    public static function isExpired(string $expiryDate): bool
    {
        return strtotime($expiryDate) < strtotime(date('Y-m-d'));
    }

    public static function controlNumber(int $heartCardId): string
    {
        return 'HC-' . str_pad((string) $heartCardId, 4, '0', STR_PAD_LEFT);
    }

    public static function displayDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        return date('F j, Y', strtotime($date));
    }
}
