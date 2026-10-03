<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the routine family: functions, procedures, aggregates, operators, operator classes and the generic object commands.
 *
 * Rule: PG-ROUTINE-001 (stub — the family implements the bodies; the method
 * signatures are the stable contract and a family may narrow a return type).
 * Scope: see `.agent/plan-pg.md`, family Routine. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class Routines
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateFunctionStmt`, `DefineStmt`, `DropStmt`, `RenameStmt` or `CommentStmt`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::production($this->lowering->productions->form($statement));
    }

    /**
     * Lowers `function_with_argtypes`: a routine named with or without its argument types.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function functionSignature(Node $signature): ObjectReference
    {
        throw ImplementationGap::production($this->lowering->productions->form($signature));
    }

    /**
     * Lowers `function_with_argtypes_list`.
     *
     * @return list<ObjectReference>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function functionSignatures(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
    }

    /**
     * Lowers `aggregate_with_argtypes`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function aggregateSignature(Node $signature): ObjectReference
    {
        throw ImplementationGap::production($this->lowering->productions->form($signature));
    }

    /**
     * Lowers `aggregate_with_argtypes_list`.
     *
     * @return list<ObjectReference>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function aggregateSignatures(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
    }

    /**
     * Lowers `operator_with_argtypes`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function operatorSignature(Node $signature): ObjectReference
    {
        throw ImplementationGap::production($this->lowering->productions->form($signature));
    }

    /**
     * Lowers `operator_with_argtypes_list`.
     *
     * @return list<ObjectReference>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function operatorSignatures(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
    }

    /**
     * Lowers an object kind: `object_type_any_name`, `object_type_name`, `drop_type_name` or `object_type_name_on_any_name`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function objectKind(Node $kind): ObjectKind
    {
        throw ImplementationGap::production($this->lowering->productions->form($kind));
    }

    /**
     * Lowers `operator_def_list`: the attributes written in ALTER OPERATOR ... SET and ALTER TYPE ... SET, each a definition whose argument may be the word NONE.
     *
     * @return list<Definition>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function operatorDefinitions(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
    }
}
