<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Explicit declarations and name lookup rules, without executing schema changes.
 * @visibility public
 * @example Distinguishing a partial declaration context
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $catalog->complete // => false
 */
final class Catalog
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<Table>
     */
    public readonly array $tables;

    /**
     * Namespace of an unqualified declaration, independent of lookup precedence.
     */
    public readonly Name $declarationSchema;

    /**
     * Keeps declaration objects themselves; the input is not an execution history.
     */
    public function __construct(
        public readonly SearchPath $searchPath,
        public readonly Comparison $tableNames = Comparison::Sensitive,
        public readonly Comparison $columnNames = Comparison::Sensitive,
        public readonly bool $complete = true,
        public readonly ?Name $currentCatalog = null,
        ?Name $declarationSchema = null,
        public readonly \SqlSemantics\Statement\Contract\LanguageProfile $profile = new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472),
        Table ...$tables,
    ) {
        $this->declarationSchema = $declarationSchema ?? $searchPath->schemas[0];
        $unique = [];
        foreach ($tables as $table) {
            \SqlSemantics\Statement\Validation\Check::input($profile->compatibleWith($table->profile), 'A declaration must belong to the context language profile.');
            $unique[spl_object_id($table)] = $table;
        }
        $this->tables = array_values($unique);
    }

    /**
     * Returns all declarations in the first matching namespace, preserving ambiguity.
     * @return list<Table>
     */
    public function matchingTables(QualifiedName $name): array
    {
        foreach ($name->schema === null ? $this->searchPath->schemas : [$name->schema] as $schema) {
            $matches = array_values(array_filter($this->tables, function (Table $table) use ($name, $schema): bool {
                $catalog = $name->catalog ?? $this->currentCatalog;
                $declarationCatalog = $table->name->catalog ?? $this->currentCatalog;
                return $this->tableNames->equal($table->name->name->value, $name->name->value)
                    && $this->tableNames->equal(($table->name->schema ?? $this->declarationSchema)->value, $schema->value)
                    && (($catalog === null && $declarationCatalog === null)
                        || ($catalog !== null && $declarationCatalog !== null && $this->tableNames->equal($catalog->value, $declarationCatalog->value)));
            }));
            if ($matches !== []) {
                return $matches;
            }
        }
        return [];
    }

}
