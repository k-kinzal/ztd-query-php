<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Temporal;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Temporal\TimestampAdd;
use SqlSemantics\Model\Scalar\Temporal\TimestampDiff;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds MySQL TIMESTAMPADD and TIMESTAMPDIFF with their unit kept apart from their operands.
 * @visibility SqlSemantics
 */
final class TimestampBinder
{
    /**
     * Returns null for every other production.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): TimestampAdd|TimestampDiff|null
    {
        $first = $source->children[0] ?? null;
        $unit = Tree::child($source, ['interval_time_stamp']);
        if ($scope->identifiers->dialect !== Dialect::MySql || $source->name !== 'function_call_nonkeyword' || !$first instanceof Token || $unit === null) {
            return null;
        }
        $name = strtoupper($first->text);
        if (!in_array($name, ['TIMESTAMPADD', 'TIMESTAMPDIFF'], true)) {
            return null;
        }
        $operands = array_map(static fn (Node $operand) => (new ExpressionBinder())->bind($operand, $scope), array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && $child->name === 'expr')));
        if (count($operands) !== 2) {
            Tree::invalid($source, 'timestamp arithmetic');
        }
        $spelled = MySqlUnit::spelled(Tree::text($unit)) ?? Tree::invalid($unit, 'interval unit');
        $version = $scope->queries?->tables->schema->grammarVersion ?? '';
        try {
            return $name === 'TIMESTAMPADD'
                ? new TimestampAdd($source, $spelled, $operands[0], $operands[1], str_starts_with($version, 'mysql-5.') ? DateArithmeticRules::Legacy : DateArithmeticRules::Current)
                : new TimestampDiff($source, $spelled, $operands[0], $operands[1]);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::TemporalOperand, $source, $error);
        }
    }
}
