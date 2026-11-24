<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Contract;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Index;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A fixed language profile with an immutable snapshot of relation and index declarations.
 *
 * The context states what is known: the declarations, whether they enumerate
 * every relation, and how names are compared. The same declaration object
 * given twice is kept once; different objects with the same name are kept as a
 * conflict. Nothing replaces a declaration, and no statement changes a context.
 * Routines, data types and session variables are not declared in a version 1
 * context; facts that need them name them as missing inputs.
 *
 * @visibility SqlSemantics
 * @example Telling an open context from a complete empty one
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     [$semantics->context()->complete, $semantics->context([])->complete] // => [false, true]
 */
final class AnalysisContext implements \SqlSemantics\AnalysisContext
{
    use Snapshot;

    /**
     * @var list<Table> The distinct declaration objects in the order given
     */
    public readonly array $tables;

    /**
     * @var list<Index> The explicit index declarations in the order supplied
     */
    public readonly array $indexes;

    /**
     * @var non-empty-list<Name> The schemas searched for an unqualified relation name, in precedence order
     */
    public readonly array $searchPath;

    /**
     * @var Name The schema an unqualified declaration belongs to
     */
    public readonly Name $declarationSchema;

    /**
     * @param LanguageProfile $profile The fixed language profile
     * @param list<Name> $searchPath The schemas searched for an unqualified relation name; at least one
     * @param list<Table> $tables The relation declarations
     * @param bool $complete Whether the declarations enumerate every relation of the database
     * @param Comparison $relationNames How relation and schema names are compared
     * @param Comparison $columnNames How column names are compared
     * @param Name|null $declarationSchema The schema an unqualified declaration belongs to; the first searched schema by default
     * @param list<Index> $indexes Explicit index declarations with absolute table owners
     */
    public function __construct(
        public readonly LanguageProfile $profile,
        array $searchPath,
        array $tables = [],
        public readonly bool $complete = true,
        public readonly Comparison $relationNames = Comparison::Sensitive,
        public readonly Comparison $columnNames = Comparison::Sensitive,
        ?Name $declarationSchema = null,
        array $indexes = [],
    ) {
        $path = Check::listOf($searchPath, Name::class, 'A context searches at least one schema.', 1);
        $unique = [];
        foreach (Check::listOf($tables, Table::class, 'Context declarations are tables.') as $table) {
            Check::input($profile->compatibleWith($table->profile), 'A declaration must belong to the language profile of the context.');
            $unique[spl_object_id($table)] = $table;
        }
        $this->searchPath = $path;
        $this->tables = array_values($unique);
        $this->indexes = Check::listOf($indexes, Index::class, 'Context indexes are an ordered list of index declarations.');
        $this->declarationSchema = $declarationSchema ?? $path[0];
    }

    /**
     * Finds the declarations with a relation name in one schema; every distinct declaration object is kept.
     *
     * @return list<Table>
     */
    public function declared(QualifiedName $name, Name $schema): array
    {
        $matches = [];
        foreach ($this->tables as $table) {
            if ($this->relationNames->equal($table->name->name->value, $name->name->value)
                && $this->relationNames->equal(($table->name->schema ?? $this->declarationSchema)->value, $schema->value)
                && ($name->catalog === null || $table->name->catalog === null || $this->relationNames->equal($name->catalog->value, $table->name->catalog->value))) {
                $matches[] = $table;
            }
        }

        return $matches;
    }

    /**
     * Finds explicit index declarations with the selected name and schema.
     *
     * @return list<Index>
     */
    public function indexesAt(Name $name, Name $schema): array
    {
        $matches = [];
        foreach ($this->indexes as $index) {
            if ($this->relationNames->equal($index->schema->value, $schema->value)
                && $this->relationNames->equal($index->name->value, $name->value)) {
                $owners = $this->declared(new QualifiedName($index->table), $index->schema);
                Check::input(
                    count($owners) === 1 && $owners[0]->kind === RelationKind::BaseTable,
                    'A declared index must have one resolved ordinary owner in its schema.'
                );
                $matches[] = $index;
            }
        }

        return $matches;
    }
}
