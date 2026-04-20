<?php

namespace App\Support;

use Illuminate\Support\Arr;

class DashboardAccess
{
    public static function can(?array $user, string $permission): bool
    {
        $permissions = self::permissions($user);

        return in_array('ROOT', $permissions, true)
            || in_array($permission, $permissions, true)
            || in_array('OP_'.$permission, $permissions, true);
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(?array $user): array
    {
        $operations = $user['operaciones'] ?? [];

        if (! is_array($operations)) {
            return [];
        }

        return collect($operations)
            ->map(fn ($operation) => self::operationCode($operation))
            ->filter()
            ->map(fn (string $code) => strtoupper($code))
            ->unique()
            ->values()
            ->all();
    }

    public static function operationCode(mixed $operation): ?string
    {
        if (is_string($operation)) {
            return $operation;
        }

        if (is_array($operation)) {
            return Arr::get($operation, 'ope_codigo')
                ?? Arr::get($operation, 'aope_codigo')
                ?? Arr::get($operation, 'codigo')
                ?? Arr::get($operation, 'code');
        }

        if (is_object($operation)) {
            return $operation->ope_codigo
                ?? $operation->aope_codigo
                ?? $operation->codigo
                ?? $operation->code
                ?? null;
        }

        return null;
    }
}
