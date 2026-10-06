<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A fixed language profile together with an immutable snapshot of relation declarations.
 *
 * The context states what is known: the declarations, whether they enumerate
 * every relation, and how names are compared. The same declaration object
 * given twice is kept once; different objects with the same name are kept as a
 * conflict. Nothing replaces a declaration, and no statement changes a context.
 * Routines, data types and session variables are not declared in a version 1
 * context; facts that need them name them as missing inputs.
 *
 * @visibility public
 * @example Telling an open context from a complete empty one
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     [$semantics->context()->complete, $semantics->context([])->complete] // => [false, true]
 */
final class AnalysisContext
{
    use Snapshot;

    /**
     * @var list<Table> The distinct declaration objects in the order given
     */
    public readonly array $tables;

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
     */
    public function __construct(
        public readonly LanguageProfile $profile,
        array $searchPath,
        array $tables = [],
        public readonly bool $complete = true,
        public readonly Comparison $relationNames = Comparison::Sensitive,
        public readonly Comparison $columnNames = Comparison::Sensitive,
        ?Name $declarationSchema = null,
    ) {
        $path = Check::listOf($searchPath, Name::class, 'A context searches at least one schema.', 1);
        $unique = [];
        foreach (Check::listOf($tables, Table::class, 'Context declarations are tables.') as $table) {
            Check::input($profile->compatibleWith($table->profile), 'A declaration must belong to the language profile of the context.');
            $unique[spl_object_id($table)] = $table;
        }
        $this->searchPath = $path;
        $this->tables = array_values($unique);
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
}
