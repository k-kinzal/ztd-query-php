<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Statement\Node as StatementNode;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the table family: tables, sequences, indexes, views, statistics, rules, triggers and policies.
 *
 * Rule: PG-TABLE-001. Scope: the statements `CreateStmt`, `CreateForeignTableStmt`,
 * `CreateAsStmt`, `CreateMatViewStmt`, `RefreshMatViewStmt`, `ViewStmt`, `AlterTableStmt`,
 * `IndexStmt`, `CreateSeqStmt`, `AlterSeqStmt`, `TruncateStmt`, `CreateStatsStmt`,
 * `AlterStatsStmt`, `CreateAssertionStmt`, `CreateTrigStmt`, `CreateEventTrigStmt`,
 * `AlterEventTrigStmt`, `RuleStmt`, `CreatePolicyStmt`, `AlterPolicyStmt`, and the pieces
 * other families share; each is lowered by the rule class named in its arm.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Tables
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateStmt`, `AlterTableStmt`, `IndexStmt` or `ViewStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        return match ($statement->name) {
            'IndexStmt' => (new IndexRule($this->lowering))->statement($statement),
            'CreateStmt' => (new CreateTableRule($this->lowering))->statement($statement),
            'CreateForeignTableStmt' => (new CreateTableRule($this->lowering))->foreign($statement),
            'CreateAsStmt' => (new CreateAsRule($this->lowering))->tableAs($statement),
            'CreateMatViewStmt' => (new CreateAsRule($this->lowering))->materializedView($statement),
            'RefreshMatViewStmt' => (new CreateAsRule($this->lowering))->refresh($statement),
            'ViewStmt' => (new CreateAsRule($this->lowering))->view($statement),
            'AlterTableStmt' => (new AlterTableRule($this->lowering))->statement($statement),
            default => $this->other($statement),
        };
    }

    /**
     * Lowers the statements of the family other than tables, indexes and views: sequences, triggers, rules, policies, statistics, TRUNCATE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function other(Node $statement): Statement
    {
        return match ($statement->name) {
            'CreateSeqStmt' => (new SequenceRule($this->lowering))->create($statement),
            'AlterSeqStmt' => (new SequenceRule($this->lowering))->alter($statement),
            'TruncateStmt' => (new MaintenanceRule($this->lowering))->truncate($statement),
            'CreateStatsStmt' => (new MaintenanceRule($this->lowering))->statistics($statement),
            'AlterStatsStmt' => (new MaintenanceRule($this->lowering))->alterStatistics($statement),
            'CreateAssertionStmt' => (new MaintenanceRule($this->lowering))->assertion($statement),
            'CreateTrigStmt' => (new TriggerRule($this->lowering))->trigger($statement),
            'CreateEventTrigStmt' => (new TriggerRule($this->lowering))->eventTrigger($statement),
            'AlterEventTrigStmt' => (new TriggerRule($this->lowering))->alterEventTrigger($statement),
            'RuleStmt' => (new RewriteRule($this->lowering))->rule($statement),
            'CreatePolicyStmt' => (new PolicyRule($this->lowering))->create($statement),
            'AlterPolicyStmt' => (new PolicyRule($this->lowering))->alter($statement),
            default => throw ImplementationGap::production($this->lowering->productions->form($statement)),
        };
    }

    /**
     * Lowers `ColQualList`: the constraints and collation written after a column or domain type.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function columnQualifiers(Node $list): array
    {
        return (new ColumnRule($this->lowering))->qualifiers($list);
    }

    /**
     * Lowers `TableConstraint`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tableConstraint(Node $constraint): Constraint
    {
        return (new ConstraintRule($this->lowering))->tableConstraint($constraint);
    }

    /**
     * Lowers `ConstraintAttributeSpec`: DEFERRABLE, INITIALLY DEFERRED, NOT VALID, NO INHERIT and their opposites, in the order written.
     *
     * @return list<ConstraintAttribute>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraintAttributes(Node $attributes): array
    {
        return (new ConstraintRule($this->lowering))->attributes($attributes);
    }

    /**
     * Lowers `index_params`: the columns and expressions of an index or a conflict target.
     *
     * @return list<IndexElement>
     */
    public function indexParameters(Node $parameters): array
    {
        return (new IndexRule($this->lowering))->indexParameters($parameters);
    }

    /**
     * Lowers `opt_unique_null_treatment`: true for NULLS DISTINCT, false for NULLS NOT DISTINCT, null when nothing is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function uniqueNullTreatment(Node $treatment): ?bool
    {
        $form = $this->lowering->productions->form($treatment);

        return match ($form->signature) {
            'opt_unique_null_treatment: NULLS_P DISTINCT' => true,
            'opt_unique_null_treatment: NULLS_P NOT DISTINCT' => false,
            'opt_unique_null_treatment:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `alter_column_default`: the expression of SET DEFAULT, or null for DROP DEFAULT.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function columnDefault(Node $change): ?Scalar
    {
        return (new AlterCommandRule($this->lowering))->defaultValue($change);
    }

    /**
     * Lowers `OptWhereClause`: a predicate written `WHERE ( expression )`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function predicate(Node $clause): ?Scalar
    {
        return (new IndexRule($this->lowering))->predicate($clause);
    }

    /**
     * Lowers `OptTemp`: the persistence written between CREATE and the object kind; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function persistence(Node $persistence): ?StatementNode
    {
        $written = (new CreateTableRule($this->lowering))->persistence($persistence);

        return $written === Persistence::Permanent ? null : $written;
    }

    /**
     * Builds CREATE TABLE ... AS EXECUTE from the `ExecuteStmt` production that writes it and the lowered EXECUTE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function createTableAsExecute(Form $form, Statement $execute): Statement
    {
        return (new CreateAsRule($this->lowering))->tableAsExecute($form, $execute);
    }
}
