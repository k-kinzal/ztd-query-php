<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An ordinary unique table constraint with a closed NULL comparison policy.
 *
 * @visibility public
 * @example Making two NULLs conflict in a unique key
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('label');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey([$column], nullsNotDistinct: true))->toString() // => 'UNIQUE NULLS NOT DISTINCT ("label") NOT DEFERRABLE'
 */
final class UniqueKey
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> Unique local key references in native index order
     */
    public readonly array $keys;

    /**
     * @var list<Identifier> Included local references in native index order
     */
    public readonly array $included;

    /**
     * Fixes the local fields and the constraint's comparison and timing policies.
     * @param list<Identifier> $keys
     * @param list<Identifier> $included
     */
    public function __construct(
        array $keys,
        array $included = [],
        public readonly ?Identifier $name = null,
        public readonly KeyTiming $timing = KeyTiming::NotDeferrable,
        public readonly bool $nullsNotDistinct = false,
    ) {
        $checkedKeys = Check::listOf($keys, Identifier::class, 'A unique key requires ordered key columns.', 1);
        $checkedIncluded = Check::listOf($included, Identifier::class, 'Unique key payload references require an ordered column list.');
        Check::input(count($checkedKeys) + count($checkedIncluded) <= 32, 'A unique index can reference at most 32 attributes.');
        $seen = [];
        foreach ($checkedKeys as $key) {
            Check::input(!isset($seen[$key->value]), 'A unique key cannot repeat a key column.');
            $seen[$key->value] = true;
        }
        $this->keys = $checkedKeys;
        $this->included = $checkedIncluded;
    }

    /**
     * Writes the ordered unique constraint within a resolved table declaration.
     */
    public function toString(): string
    {
        $keys = [];
        foreach ($this->keys as $key) {
            $keys[] = $key->quoted();
        }
        $included = [];
        foreach ($this->included as $column) {
            $included[] = $column->quoted();
        }
        $name = $this->name === null ? '' : 'CONSTRAINT ' . $this->name->quoted() . ' ';
        $payload = $included === [] ? '' : ' INCLUDE (' . implode(', ', $included) . ')';

        return $name . ($this->nullsNotDistinct ? 'UNIQUE NULLS NOT DISTINCT (' : 'UNIQUE NULLS DISTINCT (')
            . implode(', ', $keys) . ')' . $payload . ' ' . $this->timing->value;
    }
}
