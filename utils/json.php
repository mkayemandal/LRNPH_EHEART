<?php

class Json
{
    public static function encodeIds(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return json_encode($ids);
    }

    public static function decodeIds(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
