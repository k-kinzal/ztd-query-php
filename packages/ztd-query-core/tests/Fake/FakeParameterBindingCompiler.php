<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\ParameterBindingCompiler;

/**
 * A compiler that writes named parameters out as positional ones.
 *
 * Some drivers take only positional parameters, so a statement written with
 * names has to be rewritten and its values put in the order the names first
 * appear. That reordering is the part of the contract worth showing.
 */
final class FakeParameterBindingCompiler implements ParameterBindingCompiler
{
    /**
     * Rewrites a statement and its values into the form a driver will take.
     *
     * @param string $sql Statement as it was written
     * @param array<int|string, mixed>|null $params Values to bind, or null when there are none
     *
     * @return array{sql: string, params: array<int|string, mixed>|null} The statement and its values
     */
    public function compile(string $sql, ?array $params): array
    {
        if ($params === null) {
            return ['sql' => $sql, 'params' => null];
        }

        $ordered = [];
        $compiled = preg_replace_callback(
            '/:([A-Za-z_][A-Za-z0-9_]*)/',
            static function (array $match) use ($params, &$ordered): string {
                $ordered[] = $params[$match[1]] ?? null;

                return '?';
            },
            $sql,
        );

        return ['sql' => $compiled ?? $sql, 'params' => $ordered === [] ? $params : $ordered];
    }
}
