<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Rendering;

use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

/**
 * Spells explicit new scalar inputs without inventing a resolution environment.
 * @visibility SqlSemantics
 */
final class ExpressionSql
{
    /**
     * This spelling supplies expression-label constraints; it is not a bound SQL fragment.
     * @throws InvalidConstruction
     */
    public function write(C\ScalarInput $input): string
    {
        return match (true) {
            $input instanceof E\NullConstant, $input instanceof E\SqliteInteger,
            $input instanceof E\SqliteReal, $input instanceof E\SqliteText,
            $input instanceof E\SqliteBlob, $input instanceof E\SqliteCurrentTime => $input->toString(),
            $input instanceof C\Expression\GroupedInput => '(' . $input->before . $this->write($input->operand) . $input->after . ')',
            $input instanceof C\Expression\ColumnUse => ($input->qualifier === null ? '' : $input->qualifier->toString() . '.') . $input->name->toString(),
            $input instanceof C\Expression\UnaryInput => $input->operator->value . ' (' . $this->write($input->operand) . ')',
            $input instanceof C\Expression\BinaryInput => $this->binary($input),
            $input instanceof C\Expression\BetweenInput => '(' . $this->write($input->subject) . ')' . ($input->negated ? ' NOT' : '') . ' BETWEEN (' . $this->write($input->lower) . ') AND (' . $this->write($input->upper) . ')',
            $input instanceof C\Expression\InListInput => '(' . $this->write($input->subject) . ')' . ($input->negated ? ' NOT' : '') . ' IN (' . implode(', ', array_map($this->write(...), $input->choices)) . ')',
            $input instanceof C\Expression\CastInput => 'CAST(' . $this->write($input->operand) . ' AS ' . $input->target->toString() . ')',
            $input instanceof C\Expression\CollationInput => '(' . $this->write($input->operand) . ') COLLATE ' . $input->collation->toString(),
            $input instanceof C\Conditional\SearchedCaseInput => 'CASE ' . $this->branches($input->branches) . ' END',
            $input instanceof C\Conditional\SimpleCaseInput => 'CASE ' . $this->write($input->base) . ' ' . $this->branches($input->branches) . ' END',
            $input instanceof C\Subquery\ScalarQueryInput => '(' . (new QuerySql())->write($input->query) . ')',
            $input instanceof C\Subquery\ExistsInput => 'EXISTS (' . (new QuerySql())->write($input->query) . ')',
            $input instanceof C\Subquery\InQueryInput => '(' . $this->write($input->subject) . ')' . ($input->negated ? ' NOT' : '') . ' IN (' . (new QuerySql())->write($input->query) . ')',
            default => throw new InvalidConstruction('Only registered concrete scalar inputs have an SQL spelling.'),
        };
    }

    /**
     * An operator's spelling is bounded independently of its operand expressions.
     */
    public function binary(C\Expression\BinaryInput $input): string
    {
        if ($input->layout !== null && !$input->layout->groupOperands) {
            $precedence = new E\Rendering\SqlitePrecedence();
            $left = $this->write($input->left);
            $right = $this->write($input->right);
            return $input->layout->between($precedence->grouped($input->left, $input->operator, false) ? '(' . $left . ')' : $left, $precedence->grouped($input->right, $input->operator, true) ? '(' . $right . ')' : $right);
        }
        return '(' . $this->write($input->left) . ')' . ($input->layout?->symbol() ?? ' ' . $input->operator->value . ' ') . '(' . $this->write($input->right) . ')';
    }

    /**
     * Keeps branch order, the WHEN/THEN pairing, and an absent ELSE.
     */
    public function branches(C\Conditional\CaseBranchesInput $input): string
    {
        return implode(' ', array_map(fn (C\Conditional\CaseArmInput $arm): string => 'WHEN ' . $this->write($arm->when) . ' THEN ' . $this->write($arm->then), $input->arms)) . ($input->otherwise === null ? '' : ' ELSE ' . $this->write($input->otherwise));
    }
}
