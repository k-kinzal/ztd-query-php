<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Configuration\Condition\SignalAssignment;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\ResignalStatement;
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds SIGNAL and RESIGNAL outside stored programs, where no condition name or local variable is declared.
 * @visibility SqlSemantics
 */
final class Conditions
{
    /**
     * Reads the optional SQLSTATE and the SET items; a named condition or a repeated item is diagnosed.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $value = Tree::outer($node, ['signal_value'])[0] ?? null;
        $condition = $value === null ? null : self::state($value, $context);
        $assignments = self::assignments($node, $context);
        try {
            if ($node->name === 'signal_stmt') {
                return new SignalStatement($origin, $condition ?? Tree::invalid($node, 'signaled condition'), $assignments);
            }
            return new ResignalStatement($origin, $condition, $assignments);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SignalInformation, $node, $error);
        }
    }

    /**
     * Reads SQLSTATE [VALUE] 'code'; a condition name is never declared outside a stored program.
     * @throws InvalidSql
     */
    public static function state(Node $value, QueryContext $context): SqlState
    {
        $state = Tree::child($value, ['sqlstate']);
        if ($state === null) {
            throw new InvalidSql(InputViolation::ProgramReference, $value);
        }
        $tokens = $state->tokens();
        try {
            return new SqlState(MySqlNames::read($tokens[count($tokens) - 1], $context->tables->identifiers));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SignalInformation, $state, $error);
        }
    }

    /**
     * @return list<SignalAssignment>
     * @throws InvalidSql
     */
    public static function assignments(Node $node, QueryContext $context): array
    {
        $parts = Tree::outer($node, ['signal_condition_information_item_name', 'signal_allowed_expr']);
        $assignments = [];
        for ($index = 0; $index + 1 < count($parts); $index += 2) {
            $item = ConditionItem::from(strtoupper(Tree::text($parts[$index])));
            try {
                $assignments[] = new SignalAssignment($item, self::value($parts[$index + 1], $context));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::SignalInformation, $parts[$index + 1], $error);
            }
        }
        return $assignments;
    }

    /**
     * Binds a literal or variable; a bare identifier names a local variable or column no top-level statement declares, and a variable assignment is rejected.
     * @throws InvalidSql
     */
    public static function value(Node $node, QueryContext $context): Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference
    {
        if (Tree::outer($node, ['simple_ident']) !== []) {
            throw new InvalidSql(InputViolation::ProgramReference, $node);
        }
        if (in_array(':=', array_map(static fn ($token): string => $token->text, $node->tokens()), true)) {
            throw new InvalidSql(InputViolation::SignalInformation, $node);
        }
        $value = (new ExpressionBinder())->bind($node, new Scope($context->tables->identifiers, queries: $context));
        if (!$value instanceof Literal && !$value instanceof IntroducedLiteral && !$value instanceof TemporalLiteral && !$value instanceof VariableReference && !$value instanceof UnresolvedVariableReference) {
            throw new InvalidSql(InputViolation::SignalInformation, $node);
        }
        return $value;
    }
}
