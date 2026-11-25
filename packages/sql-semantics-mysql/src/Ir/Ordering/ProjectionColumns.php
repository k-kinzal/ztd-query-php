<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Binding\ParameterLowering;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCaseLowering;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\MySql\Ir\Expression\IntegralCastLowering;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthLowering;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest as ParsedNullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest as ParsedTruthTest;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Lowers the resolved MySQL output fields in their final order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProjectionColumns
{
    /**
     * Preserves output names, exact input slots and numbered parameter order.
     *
     * @return non-empty-list<ResultColumn>
     */
    public function lower(BoundStatement $operation, Select $statement, SourceBinding|JoinBinding $binding): array
    {
        $expressions = new ExpressionLowering();
        $casts = new IntegralCastLowering();
        $comparisons = new TextComparisonLowering();
        $strings = new TextLiteralLowering();
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        $parameters = new ParameterLowering();
        $parameterPosition = 0;
        $labels = new ProjectionLabel();
        foreach ($fields as $field) {
            Check::input($field->name !== null, 'The result must have a resolved name.');
            $label = $labels->lower($statement, $field, $field->name->value);
            $expression = $field->expression;
            if ($expression === null || $expression instanceof ColumnUse) {
                $resolution = $field->resolution;
                Check::input($resolution instanceof ResolvedColumn, 'A projected field must resolve to one visible input slot.');
                $results[] = new ResultColumn($binding->projected($resolution), $label);
                continue;
            }
            $operand = $expression;
            while ($operand instanceof Grouped) {
                $operand = $operand->operand;
            }
            $typed = match (true) {
                $operand instanceof ParsedTruthTest => (new TruthLowering())->lower($operand, $binding),
                $operand instanceof ParsedNullTest => (new PredicateLowering())->nullTest($operand, $binding),
                $operand instanceof CaseExpression => (new SignedCaseLowering())->lower($operand, $binding),
                $operand instanceof ParsedComparison => $comparisons->lower($operand, $binding),
                $operand instanceof Collated => (new CollatedTextLowering())->lower($operand, $binding),
                default => null,
            };
            $parameter = $parameters->lower($expression, $parameterPosition + 1);
            if ($parameter !== null) {
                ++$parameterPosition;
            }
            $results[] = new ResultColumn(
                $typed ?? $parameter ?? $strings->lower($expression) ?? $casts->lower($expression, $binding) ?? $expressions->lower($expression, $binding),
                $label,
            );
        }

        Check::input($results !== [], 'A complete projection requires at least one resolved output field.');

        return $results;
    }
}
