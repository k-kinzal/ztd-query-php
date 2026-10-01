<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Schema\Table;

/**
 * An absent catalog and an explicitly empty catalog have different meanings.
 * @visibility SqlSemantics
 */
final class Catalog
{
    /**
     * @param list<Table>|null $tables
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly ?array $tables)
    {
        foreach ($tables ?? [] as $table) {
            assert($table->dialect === $identifiers->dialect, 'A catalog uses the analysis dialect.');
        }
    }

    /**
     * Returns the unique declaration with this qualified name, if supplied.
     */
    public function find(QualifiedName $name): ?Table
    {
        $default = $this->identifiers->dialect->platform()->defaultSchema();
        $matches = [];
        foreach ($this->tables ?? [] as $table) {
            if ($this->identifiers->relationEqual($table->name->name->value, $name->name->value)
                && $this->identifiers->relationEqual($table->name->schema->value ?? $default, $name->schema->value ?? $default)) {
                $matches[] = $table;
            }
        }
        assert(count($matches) <= 1, 'A catalog has one declaration per qualified name.');
        return $matches[0] ?? null;
    }
}
