<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParametersTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RoutinesTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\TypesTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Statement\Statement;

/**
 * Lowers GRANT and REVOKE of privileges on objects.
 *
 * Rule: PG-GRANT-LOWER-001. Scope: `GrantStmt`, `RevokeStmt`,
 * `privilege_target`, `parameter_name_list`, `parameter_name`. Constructors:
 * `Grant`, `Revoke` and the `PrivilegeTarget` classes; the shared parts are
 * lowered by PG-PRIVILEGE-LOWER-001. The word TABLE before relation names
 * is noise (AccessNoise). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/sql-revoke.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class GrantRule
{
    /**
     * The kind of each target with qualified relation names, and the position of the list.
     */
    private const RELATIONS = [
        'privilege_target: qualified_name_list' => [PrivilegeObjectKind::Relation, 0],
        'privilege_target: TABLE qualified_name_list' => [PrivilegeObjectKind::Relation, 1],
        'privilege_target: SEQUENCE qualified_name_list' => [PrivilegeObjectKind::Sequence, 1],
    ];

    /**
     * The kind of each target with unqualified names, and the position of the list.
     */
    private const NAMED = [
        'privilege_target: FOREIGN DATA_P WRAPPER name_list' => [PrivilegeObjectKind::ForeignDataWrapper, 3],
        'privilege_target: FOREIGN SERVER name_list' => [PrivilegeObjectKind::ForeignServer, 2],
        'privilege_target: DATABASE name_list' => [PrivilegeObjectKind::Database, 1],
        'privilege_target: LANGUAGE name_list' => [PrivilegeObjectKind::Language, 1],
        'privilege_target: SCHEMA name_list' => [PrivilegeObjectKind::Schema, 1],
        'privilege_target: TABLESPACE name_list' => [PrivilegeObjectKind::Tablespace, 1],
    ];

    /**
     * The kind of each target with routine signatures.
     */
    private const ROUTINES = [
        'privilege_target: FUNCTION function_with_argtypes_list' => PrivilegeObjectKind::Function,
        'privilege_target: PROCEDURE function_with_argtypes_list' => PrivilegeObjectKind::Procedure,
        'privilege_target: ROUTINE function_with_argtypes_list' => PrivilegeObjectKind::Routine,
    ];

    /**
     * The kind of each target with dotted type names.
     */
    private const TYPES = [
        'privilege_target: DOMAIN_P any_name_list' => PrivilegeObjectKind::Domain,
        'privilege_target: TYPE_P any_name_list' => PrivilegeObjectKind::Type,
    ];

    /**
     * The kind of each target that covers whole schemas.
     */
    private const SCHEMAS = [
        'privilege_target: ALL TABLES IN_P SCHEMA name_list' => PrivilegeObjectKind::Relation,
        'privilege_target: ALL SEQUENCES IN_P SCHEMA name_list' => PrivilegeObjectKind::Sequence,
        'privilege_target: ALL FUNCTIONS IN_P SCHEMA name_list' => PrivilegeObjectKind::Function,
        'privilege_target: ALL PROCEDURES IN_P SCHEMA name_list' => PrivilegeObjectKind::Procedure,
        'privilege_target: ALL ROUTINES IN_P SCHEMA name_list' => PrivilegeObjectKind::Routine,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `GrantStmt` or `RevokeStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $parts = new PrivilegeRule($this->lowering);
        $behavior = $this->lowering->flags;

        return match ($form->signature) {
            'GrantStmt: GRANT privileges ON privilege_target TO grantee_list opt_grant_grant_option opt_granted_by' => new Grant($parts->privileges($form->node(1)), $this->target($form->node(3)), $parts->grantees($form->node(5)), $parts->grantOption($form->node(6)), $parts->grantor($form->node(7))),
            'RevokeStmt: REVOKE privileges ON privilege_target FROM grantee_list opt_granted_by opt_drop_behavior' => new Revoke($parts->privileges($form->node(1)), $this->target($form->node(3)), $parts->grantees($form->node(5)), false, $parts->grantor($form->node(6)), $behavior->dropBehavior($form->node(7))),
            'RevokeStmt: REVOKE GRANT OPTION FOR privileges ON privilege_target FROM grantee_list opt_granted_by opt_drop_behavior' => new Revoke($parts->privileges($form->node(4)), $this->target($form->node(6)), $parts->grantees($form->node(8)), true, $parts->grantor($form->node(9)), $behavior->dropBehavior($form->node(10))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `privilege_target`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $target): PrivilegeTarget
    {
        $form = $this->lowering->productions->form($target);
        $signature = $form->signature;
        if (isset(self::RELATIONS[$signature])) {
            $relations = [];
            foreach ($this->lowering->names->qualifiedList($form->node(self::RELATIONS[$signature][1])) as $name) {
                $relations[] = new RelationReference($name);
            }

            return new RelationsTarget(self::RELATIONS[$signature][0], $relations);
        }
        if (isset(self::NAMED[$signature])) {
            return new NamedObjectsTarget(self::NAMED[$signature][0], $this->lowering->names->names($form->node(self::NAMED[$signature][1])));
        }
        if (isset(self::ROUTINES[$signature])) {
            return new RoutinesTarget(self::ROUTINES[$signature], $this->lowering->routines->functionSignatures($form->node(1)));
        }
        if (isset(self::TYPES[$signature])) {
            return new TypesTarget(self::TYPES[$signature], $this->lowering->names->dottedList($form->node(1)));
        }
        if (isset(self::SCHEMAS[$signature])) {
            return new SchemaContentsTarget(self::SCHEMAS[$signature], $this->lowering->names->names($form->node(4)));
        }

        return match ($signature) {
            'privilege_target: LARGE_P OBJECT_P NumericOnly_list' => new LargeObjectsTarget($this->lowering->literals->signedList($form->node(2))),
            'privilege_target: PARAMETER parameter_name_list' => new ParametersTarget($this->parameters($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `parameter_name_list`; each `parameter_name` is the list of its dotted parts.
     *
     * @return list<ParameterName>
     */
    public function parameters(Node $list): array
    {
        $parameters = [];
        foreach ($this->lowering->items($list, 'parameter_name_list: parameter_name', 'parameter_name_list: parameter_name_list , parameter_name') as $parameter) {
            $parts = [];
            foreach ($this->lowering->items($parameter, 'parameter_name: ColId', 'parameter_name: parameter_name . ColId') as $part) {
                $parts[] = $this->lowering->names->name($part);
            }
            $parameters[] = new ParameterName($parts);
        }

        return $parameters;
    }
}
