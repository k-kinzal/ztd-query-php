<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Catalog;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * The type dimensions INFORMATION_SCHEMA lists for a catalog column.
 *
 * These dimensions describe the declared column, independently of the domain
 * a result reports when reading it. The display string COLUMN_TYPE is not an
 * input to consumers of these values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-columns-table.html.
 *
 * @visibility public
 * @example Reading a character column's declared lengths
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Table\Catalog\ColumnDimensions('varchar', 64, 192);
 *     [$type->name, $type->characters, $type->octets] // => ['varchar', 64, 192]
 */
final class ColumnDimensions
{
    use Snapshot;

    /**
     * @param string $name The canonical DATA_TYPE name
     * @param int|null $characters Maximum character length, when applicable
     * @param int|null $octets Maximum byte length, when applicable
     * @param int|null $precision Numeric precision, when applicable
     * @param int|null $scale Numeric scale, when applicable
     * @param int|null $fraction Fractional seconds precision, when applicable
     */
    public function __construct(
        public readonly string $name,
        public readonly ?int $characters = null,
        public readonly ?int $octets = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly ?int $fraction = null,
    ) {
        Check::input(preg_match('/\A[a-z][a-z0-9]*\z/', $name) === 1, 'A listed type has a canonical data type name.');
        foreach ([$characters, $octets, $precision, $scale, $fraction] as $dimension) {
            Check::input($dimension === null || $dimension >= 0, 'A listed type dimension is nonnegative or absent.');
        }
        Check::input(($characters === null) === ($octets === null), 'Character and octet lengths are both present or both absent.');
        Check::input($characters === null || $octets >= $characters, 'Octet length is at least character length.');
        Check::input($scale === null || ($precision !== null && $scale <= $precision), 'A numeric scale has sufficient precision.');
        Check::input($fraction === null || $fraction <= 6, 'Fractional seconds precision is at most six.');
    }
}
