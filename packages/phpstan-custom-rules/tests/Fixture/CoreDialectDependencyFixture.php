<?php

declare(strict_types=1);

namespace ZtdQuery;

final class CoreDialectDependencyFixture
{
    public function parserClass(): string
    {
        return \ZtdQuery\Platform\Postgres\PgSqlParser::class;
    }

    public function nativeDriverType(): string
    {
        return 'LONGLONG';
    }

    public function renderedCast(string $value): string
    {
        return 'CAST(' . $value . ' AS INTEGER)';
    }

    public function renderedIdentity(int $next): string
    {
        return $next . ' + ROW_NUMBER() OVER () - 1';
    }

    public function renderedInsertSelect(string $name, string $select): string
    {
        return 'WITH ' . $name . ' AS (' . $select . ') SELECT value FROM ' . $name;
    }

    public function splitDialectClass(): string
    {
        return 'ZtdQuery\\Platform\\Post' . 'gres\\PgSqlParser';
    }

    public function splitMetadataKey(): string
    {
        return 'nat' . 'ive_type';
    }

    public function joinedInsertSelect(string $columns, string $source): string
    {
        return implode(' ', ['SELECT', $columns, 'FROM', $source]);
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): array
    {
        return [
            $metadata['pdo_type'],
            $metadata['flags'],
            $metadata['precision'],
            $metadata['driver'],
        ];
    }
}
