<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionArguments;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;

/**
 * `REINDEX`: a request to rebuild indexes.
 *
 * Rule: PG-REINDEX-001. Mirrors PostgreSQL's `ReindexStmt` (kind, relation
 * or name, params) with the CONCURRENTLY word, which the server turns into
 * the option of that name. An index or a table is named by a relation name,
 * a schema by its name, and SYSTEM or DATABASE by the name of the current
 * database or by nothing. REINDEX TABLE derives the table and reports
 * relation kinds other than tables and materialized views
 * (PG-RELATION-KIND-001). Complete REINDEX INDEX resolves its index at
 * publication.
 * Diagnostics: an option REINDEX does not know; a value the option cannot
 * take; SYSTEM rebuilt concurrently, by the word or by the option.
 * Source: https://www.postgresql.org/docs/17/sql-reindex.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a schema named like a relation
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget::Schema, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'))) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
        if ($this->target === ReindexTarget::System && $this->requestsConcurrentBuild()) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::SystemConcurrently));
        }
        if ($this->target === ReindexTarget::Table && $this->object instanceof QualifiedName) {
            $kinds = new RelationKinds();
            $kind = $kinds->of($derivation->target($this, (new Targets())->resolve($derivation, $this->object)));
            $kinds->require($derivation, $kind, $this->object->name, [RelationKind::BaseTable, RelationKind::MaterializedView], KindRule::NotTableOrMaterializedView);
        }
    }

    /**
     * Resolves the last option, with the final CONCURRENTLY keyword taking precedence.
     */
    public function requestsConcurrentBuild(): bool
    {
        return $this->concurrently || (new OptionArguments())->enabled($this->options, 'concurrently', false);
    }

    /**
     * Resolves the last validated progress-reporting option.
     */
    public function reportsProgress(): bool
    {
        return (new OptionArguments())->enabled($this->options, 'verbose', false);
    }

    /**
     * Tells whether the statement requests a destination tablespace.
     */
    public function namesTablespace(): bool
    {
        return (new OptionRules())->find($this->options, 'tablespace') !== null;
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
