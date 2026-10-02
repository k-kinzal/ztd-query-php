<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Statement\Node as StatementNode;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the table family: tables, sequences, indexes, views, statistics, rules, triggers and policies.
 *
 * Rule: PG-TABLE-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Table. Status: Specified.
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
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }

    /**
     * Lowers `ColQualList`: the constraints and collation written after a column or domain type.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function columnQualifiers(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
    }

    /**
     * Lowers `TableConstraint`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function tableConstraint(Node $constraint): Clause
    {
        throw ImplementationGap::production($this->lowering->productions->form($constraint));
    }

    /**
     * Lowers `ConstraintAttributeSpec`: DEFERRABLE, INITIALLY DEFERRED, NOT VALID, NO INHERIT and their opposites, in the order written.
     *
     * @return list<StatementNode>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function constraintAttributes(Node $attributes): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($attributes));
    }

    /**
     * Lowers `index_params`: the columns and expressions of an index or a conflict target.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function indexParameters(Node $parameters): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($parameters));
    }

    /**
     * Lowers `OptWhereClause`: a predicate written `WHERE ( expression )`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function predicate(Node $clause): ?Scalar
    {
        throw ImplementationGap::production($this->lowering->productions->form($clause));
    }

    /**
     * Lowers `OptTemp`: the persistence written between CREATE and the object kind; none is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function persistence(Node $persistence): ?StatementNode
    {
        throw ImplementationGap::production($this->lowering->productions->form($persistence));
    }

    /**
     * Builds CREATE TABLE ... AS EXECUTE from the `ExecuteStmt` production that writes it and the lowered EXECUTE.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function createTableAsExecute(Form $form, Statement $execute): Statement
    {
        throw ImplementationGap::production($form);
    }
}
