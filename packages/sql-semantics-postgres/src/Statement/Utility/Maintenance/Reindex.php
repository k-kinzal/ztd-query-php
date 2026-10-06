<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionArguments;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `REINDEX`: a request to rebuild indexes.
 *
 * Rule: PG-REINDEX-001. Mirrors PostgreSQL's `ReindexStmt` (kind, relation
 * or name, params) with the CONCURRENTLY word, which the server turns into
 * the option of that name. An index or a table is named by a relation name,
 * a schema by its name, and SYSTEM or DATABASE by the name of the current
 * database or by nothing. Facts: the table of REINDEX TABLE is resolved; an
 * index, a schema and a database are not part of a declaration context; a
 * table declared as another kind than a table or a materialized view is
 * reported (PG-RELATION-KIND-001).
 * Diagnostics: an option REINDEX does not know; a value the option cannot
 * take; SYSTEM rebuilt concurrently, by the word or by the option.
 * Source: https://www.postgresql.org/docs/17/sql-reindex.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a concurrent REINDEX of a table
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REINDEX (VERBOSE) TABLE CONCURRENTLY app.t');
 *     [$operation->statement->target, $operation->statement->concurrently, $operation->statement->object->name->value] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget::Table, true, 't']
 * @example Refusing a schema named like a relation
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget::Schema, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('s'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Reindex implements Statement
{
    use Snapshot;

    /**
     * @var list<UtilityOption> The parenthesized options in the order written
     */
    public readonly array $options;

    /**
     * @param ReindexTarget $target What is rebuilt
     * @param QualifiedName|Name|null $object The relation name of an index or table, the name of a schema, or the optional name of the database
     * @param list<UtilityOption> $options The parenthesized options in the order written
     * @param bool $concurrently Whether CONCURRENTLY is written
     */
    public function __construct(
        public readonly ReindexTarget $target,
        public readonly QualifiedName|Name|null $object,
        array $options = [],
        public readonly bool $concurrently = false,
    ) {
        $this->options = Check::listOf($options, UtilityOption::class, 'The options of REINDEX are utility options.');
        Check::input(match ($target) {
            ReindexTarget::Index, ReindexTarget::Table => $object instanceof QualifiedName,
            ReindexTarget::Schema => $object instanceof Name,
            ReindexTarget::System, ReindexTarget::Database => $object === null || $object instanceof Name,
        }, 'The name does not fit what is rebuilt.');
    }

    /**
     * Resolves the table of REINDEX TABLE and reports what REINDEX rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionRules())->derive($derivation, 'REINDEX', $this->options);
        if ($this->target === ReindexTarget::System && ($this->concurrently || (new OptionArguments())->enabled($this->options, 'concurrently', false))) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::SystemConcurrently));
        }
        if ($this->target === ReindexTarget::Table && $this->object instanceof QualifiedName) {
            $kinds = new RelationKinds();
            $kind = $kinds->of($derivation->target($this, (new Targets())->resolve($derivation, $this->object)));
            $kinds->require($derivation, $kind, $this->object->name, [RelationKind::BaseTable, RelationKind::MaterializedView], KindRule::NotTableOrMaterializedView);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REINDEX');
        (new OptionRules())->write($out, $this->options, OptionSyntax::Parenthesized);
        $out->keyword($this->target->value);
        if ($this->concurrently) {
            $out->keyword('CONCURRENTLY');
        }
        if ($this->object instanceof QualifiedName) {
            (new Spelling())->qualified($out, $this->object);
        } elseif ($this->object !== null) {
            $out->name($this->object, NameUse::Column);
        }
    }
}
