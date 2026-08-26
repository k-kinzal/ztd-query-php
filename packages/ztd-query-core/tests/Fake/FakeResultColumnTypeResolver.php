<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * A resolver that reads the type a driver reports out of one key.
 *
 * What a driver calls a column's type, and under which key it reports it, is
 * the whole reason this is an interface. This reads one key so that a test
 * about the contract need not know any driver's spelling.
 */
final class FakeResultColumnTypeResolver implements ResultColumnTypeResolver
{
    /**
     * Answers the type a driver reported for a column.
     *
     * @param array<string, mixed> $metadata Column metadata as the driver reported it
     *
     * @return ColumnType The type, read as text where the driver said nothing
     */
    public function resolve(array $metadata): ColumnType
    {
        $native = $metadata['native_type'] ?? null;

        return match (is_string($native) ? strtolower($native) : '') {
            'int', 'integer' => new ColumnType(ColumnTypeFamily::INTEGER, 'int'),
            'float', 'double' => new ColumnType(ColumnTypeFamily::FLOAT, 'float'),
            default => new ColumnType(ColumnTypeFamily::TEXT, 'text'),
        };
    }
}
