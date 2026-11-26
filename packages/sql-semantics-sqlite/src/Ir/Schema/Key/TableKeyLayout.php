<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Closes all key constraints over the owning table.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableKeyLayout
{
    /**
     * Checks placement, slot integrity and the policies of equivalent indexes.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $keys
     * @param non-empty-list<TableColumn> $columns
     * @return array{list<PrimaryKey|UniqueKey>, ?PrimaryKey}
     */
    public function check(array $keys, array $columns, bool $withoutRowid): array
    {
        Check::input(array_is_list($keys), 'CREATE TABLE keys need an ordered list.');
        $checked = [];
        $primary = null;
        $lastInline = -1;
        $constraints = [];
        foreach ($keys as $key) {
            Check::input($key instanceof PrimaryKey || $key instanceof UniqueKey, 'A table key needs a checked key role.');
            $checked[] = $key;
            $this->terms($key, $columns);
            $placement = $key->inline ? $key->terms[0]->slot : count($columns);
            Check::input($placement >= $lastInline, 'Inline keys must follow owning column order and precede table keys.');
            $lastInline = $placement;
            if ($key instanceof PrimaryKey) {
                Check::input($primary === null, 'A table declaration can have one primary key.');
                $primary = $key;
                $rowidAlias = $this->rowidAlias($key, $columns, $withoutRowid);
                Check::input(!$key->autoIncrement || $rowidAlias, 'AUTOINCREMENT requires one INTEGER rowid alias.');
                if ($rowidAlias) {
                    continue;
                }
            }
            $identity = $this->indexIdentity($key);
            $prior = $constraints[$identity] ?? null;
            Check::input($prior === null || $key->conflict === null || $prior === $key->conflict, 'Equivalent table indexes have conflicting explicit ON CONFLICT clauses.');
            $constraints[$identity] = $prior ?? $key->conflict;
        }
        Check::input(!$withoutRowid || $primary !== null, 'A WITHOUT ROWID table needs a primary key.');

        return [$checked, $primary];
    }

    /**
     * Checks position membership and the inherited collation of inline clauses.
     *
     * @param non-empty-list<TableColumn> $columns
     */
    public function terms(PrimaryKey|UniqueKey $key, array $columns): void
    {
        foreach ($key->terms as $term) {
            Check::input(isset($columns[$term->slot]), 'A key term belongs to the declared column layout.');
            Check::input(!$key->inline || $term->collation === $columns[$term->slot]->collation, 'An inline key uses the owning default column collation.');
        }
    }

    /**
     * Identifies the one primary-key form that uses the rowid index.
     *
     * @param non-empty-list<TableColumn> $columns
     */
    public function rowidAlias(PrimaryKey $key, array $columns, bool $withoutRowid): bool
    {
        return !$withoutRowid && count($key->terms) === 1
            && $columns[$key->terms[0]->slot]->type === DeclaredDatatypeName::Integer
            && (!$key->inline || !$key->terms[0]->descending);
    }

    /**
     * Identifies equivalent native indexes independently of sort direction.
     */
    public function indexIdentity(PrimaryKey|UniqueKey $key): string
    {
        $identity = [];
        foreach ($key->terms as $term) {
            $identity[] = $term->slot . ':' . $term->collation->value;
        }

        return implode(',', $identity);
    }
}
