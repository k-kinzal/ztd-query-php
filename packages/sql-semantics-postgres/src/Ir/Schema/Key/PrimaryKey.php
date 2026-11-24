<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The ordered key and included fields of one ordinary native primary key.
 *
 * @visibility public
 * @example Declaring a named key on an id column
 *     $id = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('id');
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('account_pk');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey([$id], name: $name))->toString() // => 'CONSTRAINT "account_pk" PRIMARY KEY ("id") NOT DEFERRABLE'
 */
final class PrimaryKey
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> Unique key references in native index order
     */
    public readonly array $keys;

    /**
     * @var list<Identifier> Included references in native index order, with repetitions preserved
     */
    public readonly array $included;

    /**
     * Fixes the local references and timing independently of their parent field definitions.
     * @param list<Identifier> $keys
     * @param list<Identifier> $included
     */
    public function __construct(
        array $keys,
        array $included = [],
        public readonly ?Identifier $name = null,
        public readonly KeyTiming $timing = KeyTiming::NotDeferrable,
    ) {
        $checkedKeys = Check::listOf($keys, Identifier::class, 'A primary key requires ordered key columns.', 1);
        $checkedIncluded = Check::listOf($included, Identifier::class, 'Primary key payload references require an ordered column list.');
        Check::input(count($checkedKeys) + count($checkedIncluded) <= 32, 'A primary key index can reference at most 32 attributes.');
        $seen = [];
        foreach ($checkedKeys as $key) {
            Check::input(!isset($seen[$key->value]), 'A primary key cannot repeat a key column.');
            $seen[$key->value] = true;
        }
        $this->keys = $checkedKeys;
        $this->included = $checkedIncluded;
    }

    /**
     * Writes the checked constraint within its table's declaration.
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

        return $name . 'PRIMARY KEY (' . implode(', ', $keys) . ')' . $payload . ' ' . $this->timing->value;
    }
}
