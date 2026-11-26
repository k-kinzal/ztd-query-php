<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral as ParsedText;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;

/**
 * Resolves trigger row fields and the closed value forms admitted in a completed program.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerOperands
{
    /**
     * Resolves one literal or one field in the active trigger's watched table.
     * @throws InvalidConstruction When the operand is outside the checked insertion forms
     */
    public function value(BoundStatement $operation, TriggerTable $table, TableSource $watched, Scalar $operand): IntegerLiteral|TextLiteral|NullLiteral|TriggerField
    {
        $leaf = $this->ungroup($operand);
        if ($leaf instanceof ColumnUse) {
            return $this->field($operation, $table, $watched, $leaf);
        }
        if ($leaf instanceof ParsedText) {
            return new TextLiteral($leaf->value);
        }
        if ($leaf instanceof ParsedNull) {
            return new NullLiteral();
        }
        $numeric = new NumericLiteralLowering();
        $number = match (true) {
            $leaf instanceof ParsedInteger => $numeric->lower($leaf, false),
            $leaf instanceof Unary => $numeric->signed($leaf),
            default => null,
        };
        Check::input($number instanceof IntegerLiteral, 'A checked trigger insertion needs a row-image field or a supported literal.');

        return $number;
    }

    /**
     * Checks the row-image qualifier and confirms the exact declared slot.
     * @throws InvalidConstruction When the name does not identify an explicit watched column
     */
    public function field(BoundStatement $operation, TriggerTable $table, TableSource $watched, Scalar $operand): TriggerField
    {
        $leaf = $this->ungroup($operand);
        Check::input(
            $leaf instanceof ColumnUse && $leaf->qualifier !== null && $leaf->qualifier->schema === null && $leaf->qualifier->catalog === null,
            'A trigger row operand needs an explicit OLD or NEW qualifier.'
        );
        $image = match (Comparison::AsciiInsensitive->fold($leaf->qualifier->name->value)) {
            'new' => RowImage::New,
            'old' => RowImage::Old,
            default => throw new InvalidConstruction('A trigger row operand needs an explicit OLD or NEW qualifier.'),
        };
        $fact = $operation->facts->scalar($leaf)->resolution;
        Check::input(
            $fact instanceof ResolvedColumn && $fact->relation === $table && $fact->merge === null,
            'A trigger operand must resolve to the active watched relation.'
        );
        $key = (new Identifier($leaf->name->value))->key();
        foreach ($watched->columns as $position => $column) {
            if ($column->key() === $key) {
                return new TriggerField($image, $position);
            }
        }

        throw new InvalidConstruction('A trigger operand needs an explicit watched column.');
    }

    /**
     * Resolves a native NULL test over one trigger row image.
     * @throws InvalidConstruction When WHEN is outside the checked condition forms
     */
    public function condition(BoundStatement $operation, TriggerTable $table, TableSource $watched, ?Scalar $operand): ?TriggerCondition
    {
        if ($operand === null) {
            return null;
        }
        $test = $this->ungroup($operand);
        if ($test instanceof NullTest) {
            return new TriggerCondition($this->field($operation, $table, $watched, $test->operand), $test->form === NullTestForm::IsNull);
        }
        Check::input($test instanceof Binary, 'A checked trigger condition needs a NULL test of a watched field.');
        $left = $this->ungroup($test->left);
        $right = $this->ungroup($test->right);
        Check::input($left instanceof ParsedNull || $right instanceof ParsedNull, 'A checked trigger condition needs a NULL test of a watched field.');
        $operator = $test->operator;
        Check::input(
            in_array($operator, [BinaryOperator::Is, BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom, BinaryOperator::IsNotDistinctFrom], true),
            'A checked trigger condition needs a NULL test of a watched field.'
        );
        $isNull = $operator === BinaryOperator::Is || $operator === BinaryOperator::IsNotDistinctFrom;

        return new TriggerCondition($this->field($operation, $table, $watched, $left instanceof ParsedNull ? $right : $left), $isNull);
    }

    /**
     * Removes grouping around an operand with no enclosing precedence dependence.
     */
    public function ungroup(Scalar $operand): Scalar
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        return $operand;
    }
}
