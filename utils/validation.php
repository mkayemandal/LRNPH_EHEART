<?php

class Validation
{
    public static function requireFields(array $input, array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
                $missing[] = $field;
            }
            if (is_array($input[$field] ?? null) && count($input[$field]) === 0) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    public static function isValidCoreValueArray($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (!is_int($v) && !ctype_digit((string) $v)) {
                return false;
            }
        }
        return count($value) > 0;
    }
}
