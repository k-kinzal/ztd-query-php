<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules;
use SqlSemantics\Model\Scalar\Temporal\DateShift;
use SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Temporal\ShiftDirection;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\QueryComparison;

/**
 * Classifies infix intervals and DATE_ADD/DATE_SUB forms before generic operator or function binding.
 * @visibility SqlSemantics
 */
final class DateShiftBinder
{
    /**
     * Keeps the temporal input, interval quantity, unit, and direction in separate roles.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $source, Scope $scope): ?DateShift
    {
        if ($scope->identifiers->dialect !== Dialect::MySql || !in_array($source->name, ['bit_expr', 'simple_expr', 'function_call_nonkeyword'], true)) {
            return null;
        }
        $first = $source->children[0] ?? null;
        $name = $first instanceof Token ? strtoupper($first->text) : '';
        $call = $source->name === 'function_call_nonkeyword' && in_array($name, ['ADDDATE', 'SUBDATE', 'DATE_ADD', 'DATE_SUB'], true);
        $interval = Tree::child($source, ['interval']);
        if (!$call && ($source->name === 'function_call_nonkeyword' || $interval === null)) {
            return null;
        }
        $operands = array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && in_array($child->name, ['expr', 'bit_expr'], true)));
        if (count($operands) !== 2) {
            throw new UnclassifiedSql('Unclassified temporal operand production: ' . $source->toString());
        }
        if ($name === 'INTERVAL') {
            $operands = array_reverse($operands);
        }
        $value = (new ExpressionBinder())->bind($operands[0], $scope);
        $quantity = (new ExpressionBinder())->bind($operands[1], $scope);
        if (!in_array(QueryComparison::width($value), [null, 1], true) || !in_array(QueryComparison::width($quantity), [null, 1], true)) {
            throw new InvalidSql(InputViolation::TemporalOperand, $source);
        }
        $tokens = array_map(static fn (Token $token): string => strtoupper($token->text), array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Token)));
        $direction = in_array('-', $tokens, true) || in_array($name, ['DATE_SUB', 'SUBDATE'], true) ? ShiftDirection::Subtract : ShiftDirection::Add;
        $unit = $interval === null ? MySqlUnit::Day : MySqlUnit::from(strtoupper(Tree::text($interval)));
        $version = $scope->queries?->tables->schema->grammarVersion ?? '';
        $rules = str_starts_with($version, 'mysql-5.') ? DateArithmeticRules::Legacy : DateArithmeticRules::Current;
        return new DateShift($source, $value, $quantity, $unit, $direction, $rules, $name === 'INTERVAL' ? IntervalOperandOrder::IntervalFirst : IntervalOperandOrder::TemporalFirst);
    }
}
