<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the routine family: functions, procedures, aggregates, operators, operator classes and the generic object commands.
 *
 * Rule: PG-ROUTINE-001. Every other family reaches the routine rules through
 * these methods: PG-FUNCTION-LOWER-001, PG-ROUTINE-OPTION-LOWER-001,
 * PG-SIGNATURE-LOWER-001, PG-DEFINE-LOWER-001, PG-OPCLASS-LOWER-001,
 * PG-DROP-LOWER-001, PG-COMMENT-LOWER-001, PG-RENAME-LOWER-001,
 * PG-ALTER-OBJECT-LOWER-001 and PG-OBJECT-LOWER-001. Status: Implemented.
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
     * @throws ImplementationGap When the statement is not one of the family
     */
    public function statement(Node $statement): Statement
    {
        return match ($statement->name) {
            'CreateFunctionStmt' => (new FunctionRule($this->lowering))->create($statement),
            'AlterFunctionStmt' => (new OptionRule($this->lowering))->alter($statement),
            'DropStmt', 'RemoveFuncStmt', 'RemoveAggrStmt', 'RemoveOperStmt', 'DropOpClassStmt', 'DropOpFamilyStmt' => (new DropRule($this->lowering))->drop($statement),
            'DefineStmt' => (new DefineRule($this->lowering))->define($statement),
            'CreateOpClassStmt', 'CreateOpFamilyStmt', 'AlterOpFamilyStmt', 'AlterOperatorStmt' => (new OperatorClassRule($this->lowering))->statement($statement),
            'CommentStmt' => (new CommentRule($this->lowering))->comment($statement),
            'SecLabelStmt' => (new CommentRule($this->lowering))->label($statement),
            'RenameStmt' => (new RenameRule($this->lowering))->rename($statement),
            'AlterObjectSchemaStmt' => (new AlterObjectRule($this->lowering))->schema($statement),
            'AlterOwnerStmt' => (new AlterObjectRule($this->lowering))->owner($statement),
            'AlterObjectDependsStmt' => (new AlterObjectRule($this->lowering))->depends($statement),
            default => throw ImplementationGap::production($this->lowering->productions->form($statement)),
        };
    }

    /**
     * Lowers `function_with_argtypes`: a routine named with or without its argument types.
     */
    public function functionSignature(Node $signature): RoutineSignature
    {
        return (new SignatureRule($this->lowering))->function($signature);
    }

    /**
     * Lowers `function_with_argtypes_list`.
     *
     * @return list<RoutineSignature>
     */
    public function functionSignatures(Node $list): array
    {
        return (new SignatureRule($this->lowering))->functions($list);
    }

    /**
     * Lowers `aggregate_with_argtypes`.
     */
    public function aggregateSignature(Node $signature): AggregateSignature
    {
        return (new SignatureRule($this->lowering))->aggregate($signature);
    }

    /**
     * Lowers `aggregate_with_argtypes_list`.
     *
     * @return list<AggregateSignature>
     */
    public function aggregateSignatures(Node $list): array
    {
        return (new SignatureRule($this->lowering))->aggregates($list);
    }

    /**
     * Lowers `operator_with_argtypes`.
     */
    public function operatorSignature(Node $signature): OperatorSignature
    {
        return (new SignatureRule($this->lowering))->operator($signature);
    }

    /**
     * Lowers `operator_with_argtypes_list`.
     *
     * @return list<OperatorSignature>
     */
    public function operatorSignatures(Node $list): array
    {
        return (new SignatureRule($this->lowering))->operators($list);
    }

    /**
     * Lowers an object kind: `object_type_any_name`, `object_type_name`, `drop_type_name` or `object_type_name_on_any_name`.
     */
    public function objectKind(Node $kind): ObjectKind
    {
        return (new ObjectRule($this->lowering))->kind($kind);
    }

    /**
     * Lowers `operator_def_list`: the attributes written in ALTER OPERATOR ... SET and ALTER TYPE ... SET, each a definition whose argument may be the word NONE.
     *
     * @return list<Definition>
     */
    public function operatorDefinitions(Node $list): array
    {
        return (new OperatorClassRule($this->lowering))->definitions($list);
    }
}
