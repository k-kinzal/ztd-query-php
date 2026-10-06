<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE SCHEMA.
 *
 * Rule: PG-SCHEMA-LOWER-001. Scope: `CreateSchemaStmt`, `OptSchemaEltList`,
 * `schema_stmt`. Constructor: `CreateSchema`. Each element is lowered by the
 * family of its statement. Termination: the element list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createschema.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class SchemaRule
{
    /**
     * The productions of `schema_stmt`, each a statement of another family.
     */
    private const ELEMENTS = [
        'schema_stmt: CreateStmt', 'schema_stmt: IndexStmt', 'schema_stmt: CreateSeqStmt',
        'schema_stmt: CreateTrigStmt', 'schema_stmt: GrantStmt', 'schema_stmt: ViewStmt',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateSchemaStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $roles = $this->lowering->roles;

        return match ($form->signature) {
            'CreateSchemaStmt: CREATE SCHEMA opt_single_name AUTHORIZATION RoleSpec OptSchemaEltList' => new CreateSchema($names->optional($form->node(2)), $roles->role($form->node(4)), $this->elements($form->node(5))),
            'CreateSchemaStmt: CREATE SCHEMA ColId OptSchemaEltList' => new CreateSchema($names->name($form->node(2)), null, $this->elements($form->node(3))),
            'CreateSchemaStmt: CREATE SCHEMA IF_P NOT EXISTS opt_single_name AUTHORIZATION RoleSpec OptSchemaEltList' => new CreateSchema($names->optional($form->node(5)), $roles->role($form->node(7)), $this->elements($form->node(8)), true),
            'CreateSchemaStmt: CREATE SCHEMA IF_P NOT EXISTS ColId OptSchemaEltList' => new CreateSchema($names->name($form->node(5)), null, $this->elements($form->node(6)), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptSchemaEltList`: the element statements in order.
     *
     * @return list<Statement>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function elements(Node $list): array
    {
        $elements = [];
        foreach ($this->lowering->items($list, 'OptSchemaEltList: OptSchemaEltList schema_stmt', 'OptSchemaEltList:') as $item) {
            $form = $this->lowering->productions->form($item);
            if (!in_array($form->signature, self::ELEMENTS, true)) {
                throw ImplementationGap::production($form);
            }
            $elements[] = $this->lowering->statement($form->node(0));
        }

        return $elements;
    }
}
