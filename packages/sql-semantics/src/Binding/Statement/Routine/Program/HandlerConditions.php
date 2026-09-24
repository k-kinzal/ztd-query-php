<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionClass;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\NamedCondition;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the conditions a handler catches and the values DECLARE ... CONDITION names.
 * @visibility SqlSemantics
 */
final class HandlerConditions
{
    /**
     * Binds a handler's condition list; a condition handled twice in one block is diagnosed, comparing named conditions by value.
     * @param array<string, true> $handled Conditions handled by earlier handlers of the block, updated in place
     * @return list<ErrorCode|SqlState|NamedCondition|ConditionClass>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function list(Node $list, ProgramFrame $frame, array &$handled): array
    {
        $conditions = [];
        foreach (Tree::outer($list, ['sp_hcond']) as $node) {
            $condition = self::condition($node, $frame);
            $key = $condition instanceof NamedCondition ? HandlerDeclaration::key($frame->conditions[strtolower($condition->name)]) : HandlerDeclaration::key($condition);
            if (isset($handled[$key])) {
                throw new InvalidSql(InputViolation::ProgramDeclaration, $node);
            }
            $handled[$key] = true;
            $conditions[] = $condition;
        }
        return $conditions;
    }

    /**
     * Binds one handled condition: an error number, an SQLSTATE, a declared condition name or a condition class.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function condition(Node $node, ProgramFrame $frame): ErrorCode|SqlState|NamedCondition|ConditionClass
    {
        $value = Tree::child($node, ['sp_cond']);
        if ($value !== null) {
            return self::value($value, $frame);
        }
        $name = Tree::child($node, ['ident']);
        if ($name !== null) {
            $condition = $frame->context->tables->identifiers->name($name->tokens()[0]);
            if (!isset($frame->conditions[strtolower($condition)])) {
                throw new InvalidSql(InputViolation::ProgramObject, $name);
            }
            return new NamedCondition($condition);
        }
        $tokens = $node->tokens();
        $word = strtoupper($tokens[count($tokens) - 1]->text ?? '');
        return match ($word) {
            'FOUND' => ConditionClass::NotFound,
            'SQLWARNING' => ConditionClass::SqlWarning,
            'SQLEXCEPTION' => ConditionClass::SqlException,
            default => throw new UnclassifiedSql('Unclassified handler condition: ' . $word),
        };
    }

    /**
     * Binds an error number other than 0 or a valid SQLSTATE other than a completion condition.
     * @throws InvalidSql
     */
    public static function value(Node $node, ProgramFrame $frame): ErrorCode|SqlState
    {
        $tokens = $node->tokens();
        $last = $tokens[count($tokens) - 1] ?? throw new InvalidSql(InputViolation::ProgramDeclaration, $node);
        try {
            return Tree::child($node, ['sqlstate']) !== null ? new SqlState(MySqlNames::read($last, $frame->context->tables->identifiers)) : new ErrorCode($last->text);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ProgramDeclaration, $node, $error);
        }
    }
}
