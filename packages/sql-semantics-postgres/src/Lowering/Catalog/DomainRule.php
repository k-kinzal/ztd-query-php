<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\AlterDomainConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\AlterDomainNotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\CreateDomain;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\DomainCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\DomainDefaultChange;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\DomainNotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\DropDomainConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Domain\ValidateDomainConstraint;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the domain commands.
 *
 * Rule: PG-DOMAIN-LOWER-001. Scope: `CreateDomainStmt`, `AlterDomainStmt`,
 * `DomainConstraint`, `DomainConstraintElem`. Constructors: `CreateDomain`,
 * `DomainDefaultChange`, `AlterDomainNotNull`, `AlterDomainConstraint`,
 * `DomainCheck`, `DomainNotNull`, `DropDomainConstraint`,
 * `ValidateDomainConstraint`. Column qualifiers, table constraints, constraint
 * attributes and defaults are lowered by the table family. The optional AS
 * is noise (LeafNoise). Source: https://www.postgresql.org/docs/17/sql-createdomain.html,
 * https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class DomainRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a domain command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $name = $this->lowering->names->dotted($form->node(2));
        $tables = $this->lowering->tables;
        $names = $this->lowering->names;

        return match ($form->signature) {
            'CreateDomainStmt: CREATE DOMAIN_P any_name opt_as Typename ColQualList' => new CreateDomain($name, $this->lowering->types->typeName($form->node(4)), $tables->columnQualifiers($form->node(5))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name alter_column_default' => new DomainDefaultChange($name, $tables->columnDefault($form->node(3))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name DROP NOT NULL_P' => new AlterDomainNotNull($name, false),
            'AlterDomainStmt: ALTER DOMAIN_P any_name SET NOT NULL_P' => new AlterDomainNotNull($name, true),
            'AlterDomainStmt: ALTER DOMAIN_P any_name ADD_P TableConstraint' => new AlterDomainConstraint($name, $tables->tableConstraint($form->node(4))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name ADD_P DomainConstraint' => new AlterDomainConstraint($name, $this->constraint($form->node(4))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name DROP CONSTRAINT name opt_drop_behavior' => new DropDomainConstraint($name, $names->name($form->node(5)), false, $this->lowering->flags->dropBehavior($form->node(6))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name DROP CONSTRAINT IF_P EXISTS name opt_drop_behavior' => new DropDomainConstraint($name, $names->name($form->node(7)), true, $this->lowering->flags->dropBehavior($form->node(8))),
            'AlterDomainStmt: ALTER DOMAIN_P any_name VALIDATE CONSTRAINT name' => new ValidateDomainConstraint($name, $names->name($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `DomainConstraint`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraint(Node $constraint): DomainCheck|DomainNotNull
    {
        $form = $this->lowering->productions->form($constraint);

        return match ($form->signature) {
            'DomainConstraint: CONSTRAINT name DomainConstraintElem' => $this->element($form->node(2), $this->lowering->names->name($form->node(1))),
            'DomainConstraint: DomainConstraintElem' => $this->element($form->node(0), null),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `DomainConstraintElem` with the constraint name written before it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function element(Node $element, ?Name $name): DomainCheck|DomainNotNull
    {
        $form = $this->lowering->productions->form($element);
        $tables = $this->lowering->tables;

        return match ($form->signature) {
            'DomainConstraintElem: CHECK ( a_expr ) ConstraintAttributeSpec' => new DomainCheck($name, $this->lowering->expressions->expression($form->node(2)), $tables->constraintAttributes($form->node(4))),
            'DomainConstraintElem: NOT NULL_P ConstraintAttributeSpec' => new DomainNotNull($name, $tables->constraintAttributes($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }
}
