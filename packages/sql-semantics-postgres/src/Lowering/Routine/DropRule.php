<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;

/**
 * Lowers the DROP statements of the routine family.
 *
 * Rule: PG-DROP-LOWER-001. Scope: `DropStmt`, `RemoveFuncStmt`,
 * `RemoveAggrStmt`, `RemoveOperStmt`, `DropOpClassStmt`, `DropOpFamilyStmt`.
 * Constructor: `Drop`, as PostgreSQL builds one `DropStmt` for all of
 * them. Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-droptable.html, https://www.postgresql.org/docs/17/sql-dropfunction.html,
 * https://www.postgresql.org/docs/17/sql-dropopclass.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropRule
{
    /**
     * The kind each routine, aggregate and operator DROP production removes.
     */
    private const SIGNED = [
        'RemoveFuncStmt: DROP FUNCTION function_with_argtypes_list opt_drop_behavior' => ObjectKind::Function,
        'RemoveFuncStmt: DROP FUNCTION IF_P EXISTS function_with_argtypes_list opt_drop_behavior' => ObjectKind::Function,
        'RemoveFuncStmt: DROP PROCEDURE function_with_argtypes_list opt_drop_behavior' => ObjectKind::Procedure,
        'RemoveFuncStmt: DROP PROCEDURE IF_P EXISTS function_with_argtypes_list opt_drop_behavior' => ObjectKind::Procedure,
        'RemoveFuncStmt: DROP ROUTINE function_with_argtypes_list opt_drop_behavior' => ObjectKind::Routine,
        'RemoveFuncStmt: DROP ROUTINE IF_P EXISTS function_with_argtypes_list opt_drop_behavior' => ObjectKind::Routine,
        'RemoveAggrStmt: DROP AGGREGATE aggregate_with_argtypes_list opt_drop_behavior' => ObjectKind::Aggregate,
        'RemoveAggrStmt: DROP AGGREGATE IF_P EXISTS aggregate_with_argtypes_list opt_drop_behavior' => ObjectKind::Aggregate,
        'RemoveOperStmt: DROP OPERATOR operator_with_argtypes_list opt_drop_behavior' => ObjectKind::Operator,
        'RemoveOperStmt: DROP OPERATOR IF_P EXISTS operator_with_argtypes_list opt_drop_behavior' => ObjectKind::Operator,
        'DropOpClassStmt: DROP OPERATOR CLASS any_name USING name opt_drop_behavior' => ObjectKind::OperatorClass,
        'DropOpClassStmt: DROP OPERATOR CLASS IF_P EXISTS any_name USING name opt_drop_behavior' => ObjectKind::OperatorClass,
        'DropOpFamilyStmt: DROP OPERATOR FAMILY any_name USING name opt_drop_behavior' => ObjectKind::OperatorFamily,
        'DropOpFamilyStmt: DROP OPERATOR FAMILY IF_P EXISTS any_name USING name opt_drop_behavior' => ObjectKind::OperatorFamily,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a DROP statement of the family.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function drop(Node $statement): Drop
    {
        $form = $this->lowering->productions->form($statement);
        $ifExists = (new ObjectRule($this->lowering))->has($form, 'IF_P');
        $behavior = $this->lowering->flags->dropBehavior($form->node(count($form->node->children) - 1));
        $kind = self::SIGNED[$form->signature] ?? null;
        if ($kind !== null) {
            return new Drop($kind, $this->signed($form, $kind, $ifExists ? 4 : 2), $ifExists, $behavior);
        }
        if ($form->signature === 'DropStmt: DROP INDEX CONCURRENTLY any_name_list opt_drop_behavior' || $form->signature === 'DropStmt: DROP INDEX CONCURRENTLY IF_P EXISTS any_name_list opt_drop_behavior') {
            return new Drop(ObjectKind::Index, $this->lowering->names->dottedList($form->node($ifExists ? 5 : 3)), $ifExists, $behavior, true);
        }

        return $this->generic($form, $ifExists, $behavior);
    }

    /**
     * Lowers the objects of a routine, aggregate, operator, operator class or operator family DROP.
     *
     * @return list<ObjectReference>
     */
    public function signed(Form $form, ObjectKind $kind, int $at): array
    {
        $signatures = new SignatureRule($this->lowering);
        $names = $this->lowering->names;

        return match ($kind) {
            ObjectKind::Aggregate => $signatures->aggregates($form->node($at)),
            ObjectKind::Operator => $signatures->operators($form->node($at)),
            ObjectKind::OperatorClass, ObjectKind::OperatorFamily => [new OperatorGroupName($names->dotted($form->node($at + 1)), $names->name($form->node($at + 3)))],
            default => $signatures->functions($form->node($at)),
        };
    }

    /**
     * Lowers the `DropStmt` productions that name their kind with a kind nonterminal, TYPE or DOMAIN.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function generic(Form $form, bool $ifExists, ?\SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior $behavior): Drop
    {
        $objects = new ObjectRule($this->lowering);
        $names = $this->lowering->names;
        $at = $ifExists ? 4 : 2;
        [$kind, $list] = match ($form->signature) {
            'DropStmt: DROP object_type_any_name IF_P EXISTS any_name_list opt_drop_behavior', 'DropStmt: DROP object_type_any_name any_name_list opt_drop_behavior' => [$objects->kind($form->node(1)), $names->dottedList($form->node($at))],
            'DropStmt: DROP drop_type_name IF_P EXISTS name_list opt_drop_behavior', 'DropStmt: DROP drop_type_name name_list opt_drop_behavior' => [$objects->kind($form->node(1)), $this->unqualified($names->names($form->node($at)))],
            'DropStmt: DROP object_type_name_on_any_name name ON any_name opt_drop_behavior', 'DropStmt: DROP object_type_name_on_any_name IF_P EXISTS name ON any_name opt_drop_behavior' => [$objects->kind($form->node(1)), [new MemberName($names->name($form->node($at)), $names->dotted($form->node($at + 2)))]],
            'DropStmt: DROP TYPE_P type_name_list opt_drop_behavior', 'DropStmt: DROP TYPE_P IF_P EXISTS type_name_list opt_drop_behavior' => [ObjectKind::Type, $this->types($form->node($at))],
            'DropStmt: DROP DOMAIN_P type_name_list opt_drop_behavior', 'DropStmt: DROP DOMAIN_P IF_P EXISTS type_name_list opt_drop_behavior' => [ObjectKind::Domain, $this->types($form->node($at))],
            default => throw ImplementationGap::production($form),
        };

        return new Drop($kind, $list, $ifExists, $behavior);
    }

    /**
     * Answers each name as an unqualified object name.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $names
     *
     * @return list<UnqualifiedName>
     */
    public function unqualified(array $names): array
    {
        $objects = [];
        foreach ($names as $name) {
            $objects[] = new UnqualifiedName($name);
        }

        return $objects;
    }

    /**
     * Lowers `type_name_list` into type references.
     *
     * @return list<TypeReference>
     */
    public function types(Node $list): array
    {
        $objects = [];
        foreach ($this->lowering->types->typeNames($list) as $type) {
            $objects[] = new TypeReference($type);
        }

        return $objects;
    }
}
