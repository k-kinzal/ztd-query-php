<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Prepared\Deallocate;
use SqlSemantics\Platform\PostgreSql\Statement\Prepared\Execute;
use SqlSemantics\Platform\PostgreSql\Statement\Prepared\Prepare;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers PREPARE, EXECUTE and DEALLOCATE.
 *
 * Rule: PG-PREPARED-LOWER-001. Scope: `PrepareStmt`, `prep_type_clause`,
 * `ExecuteStmt`, `execute_param_clause`, `DeallocateStmt`. Constructors:
 * `Prepare`, `Execute`, `Deallocate`; CREATE TABLE AS EXECUTE is built by
 * the table family around the `Execute`. PREPARE after DEALLOCATE is a
 * noise word.
 * Source: https://www.postgresql.org/docs/17/sql-prepare.html, https://www.postgresql.org/docs/17/sql-execute.html,
 * https://www.postgresql.org/docs/17/sql-deallocate.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PreparedRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `PrepareStmt`.
     *
     * @throws ImplementationGap When the production has no rule, or the statement prepared is not a query
     */
    public function prepare(Node $statement): Prepare
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'PrepareStmt: PREPARE name prep_type_clause AS PreparableStmt') {
            throw ImplementationGap::production($form);
        }
        $name = $this->lowering->names->name($form->node(1));
        $types = $this->lowering->productions->form($form->node(2));
        $types = match ($types->signature) {
            'prep_type_clause: ( type_list )' => $this->lowering->types->typeNames($types->node(1)),
            'prep_type_clause:' => [],
            default => throw ImplementationGap::production($types),
        };
        $prepared = $this->lowering->manipulations->preparable($form->node(4));
        if (!$prepared instanceof Query) {
            throw ImplementationGap::rule('PG-PREPARED-LOWER-001: a prepared statement that is not a query');
        }

        return new Prepare($name, $types, $prepared);
    }

    /**
     * Lowers `ExecuteStmt`: EXECUTE, or CREATE TABLE AS EXECUTE through the table family.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function execute(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'ExecuteStmt: EXECUTE name execute_param_clause' => new Execute($this->lowering->names->name($form->node(1)), $this->parameters($form->node(2))),
            'ExecuteStmt: CREATE OptTemp TABLE create_as_target AS EXECUTE name execute_param_clause opt_with_data' => $this->lowering->tables->createTableAsExecute(
                $form,
                new Execute($this->lowering->names->name($form->node(6)), $this->parameters($form->node(7))),
            ),
            'ExecuteStmt: CREATE OptTemp TABLE IF_P NOT EXISTS create_as_target AS EXECUTE name execute_param_clause opt_with_data' => $this->lowering->tables->createTableAsExecute(
                $form,
                new Execute($this->lowering->names->name($form->node(9)), $this->parameters($form->node(10))),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `execute_param_clause`; no clause is an empty list.
     *
     * @return list<Scalar>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parameters(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'execute_param_clause: ( expr_list )' => $this->lowering->expressions->expressions($form->node(1)),
            'execute_param_clause:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `DeallocateStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function deallocate(Node $statement): Deallocate
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'DeallocateStmt: DEALLOCATE name' => new Deallocate($this->lowering->names->name($form->node(1))),
            'DeallocateStmt: DEALLOCATE PREPARE name' => new Deallocate($this->lowering->names->name($form->node(2))),
            'DeallocateStmt: DEALLOCATE ALL', 'DeallocateStmt: DEALLOCATE PREPARE ALL' => new Deallocate(null),
            default => throw ImplementationGap::production($form),
        };
    }
}
