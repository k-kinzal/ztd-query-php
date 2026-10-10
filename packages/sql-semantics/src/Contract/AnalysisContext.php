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
 * Routines and data types are not declared in a version 1 context; facts that
 * need them name them as missing inputs. The session, when given, holds the
 * session variables the platform resolves statements with.
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
     * @param Session|null $session The session statements are resolved in, or null for a new session with the server defaults
     * @param list<string> $foldedSchemas The schemas whose name and relation names compare without regard to ASCII case, whatever relationNames says
     */
    public function __construct(
        public readonly LanguageProfile $profile,
        array $searchPath,
        array $tables = [],
        public readonly bool $complete = true,
        public readonly Comparison $relationNames = Comparison::Sensitive,
        public readonly Comparison $columnNames = Comparison::Sensitive,
        ?Name $declarationSchema = null,
        public readonly ?Session $session = null,
        public readonly array $foldedSchemas = [],
    ) {
        $path = Check::listOf($searchPath, Name::class, 'A context searches at least one schema.', 1);
        $unique = [];
        foreach (Check::listOf($tables, Table::class, 'Context declarations are tables.') as $table) {
            Check::input($profile->sharesDeclarationsWith($table->profile), 'A declaration must belong to the grammar release of the context.');
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
        $names = $this->folded($schema->value) ? Comparison::AsciiInsensitive : $this->relationNames;
        foreach ($this->tables as $table) {
            if ($names->equal($table->name->name->value, $name->name->value)
                && $names->equal(($table->name->schema ?? $this->declarationSchema)->value, $schema->value)
                && ($name->catalog === null || $table->name->catalog === null || $this->relationNames->equal($name->catalog->value, $table->name->catalog->value))) {
                $matches[] = $table;
            }
        }

        return $matches;
    }

    /**
     * Tells whether a schema is one whose name and relation names compare without regard to ASCII case.
     */
    public function folded(string $schema): bool
    {
        foreach ($this->foldedSchemas as $folded) {
            if (Comparison::AsciiInsensitive->equal($folded, $schema)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the same context resolving statements in another session.
     */
    public function withSession(?Session $session): self
    {
        return new self($this->profile, $this->searchPath, $this->tables, $this->complete, $this->relationNames, $this->columnNames, $this->declarationSchema, $session, $this->foldedSchemas);
    }

}
