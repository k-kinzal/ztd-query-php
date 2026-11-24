<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerEquality;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetNullTest;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;

/**
 * Resolves local and row-image positions for a single checked trigger mutation.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerMutationOperands
{
    /**
     * Fixes the transient bindings and never exports them to the published trigger.
     */
    public function __construct(
        private readonly BoundStatement $operation,
        private readonly TriggerTable $source,
        private readonly TableSource $watched,
        private readonly SourceBinding $target,
    ) {
    }

    /**
     * Resolves an assignment's scalar value and preserves local and image roles.
     */
    public function value(Scalar $operand): IntegerLiteral|TextLiteral|NullLiteral|TriggerField|TriggerTargetField
    {
        $operands = new TriggerOperands();
        $leaf = $operands->ungroup($operand);

        return $leaf instanceof ColumnUse
            ? $this->field($leaf) : $operands->value($this->operation, $this->source, $this->watched, $leaf);
    }

    /**
     * Resolves the exact source occurrence before choosing an emitted qualifier.
     */
    public function field(Scalar $operand): TriggerField|TriggerTargetField
    {
        $images = new TriggerOperands();
        $leaf = $images->ungroup($operand);
        Check::input($leaf instanceof ColumnUse, 'A trigger mutation filter requires resolved local or row-image fields.');
        $position = $this->operation->facts->scalar($leaf)->resolution;
        Check::input($position instanceof ResolvedColumn && $position->merge === null, 'A trigger mutation field must have one resolved occurrence.');
        if ($position->relation === $this->source) {
            return $images->field($this->operation, $this->source, $this->watched, $leaf);
        }

        return new TriggerTargetField($this->target->resolved($position)->slot);
    }

    /**
     * Lowers a supported condition with its written native comparison order.
     */
    public function filter(?Scalar $operand): TriggerEquality|TriggerCondition|TriggerTargetNullTest|null
    {
        if ($operand === null) {
            return null;
        }
        $images = new TriggerOperands();
        $test = $images->ungroup($operand);
        if ($test instanceof NullTest) {
            return $this->nullTest($this->field($test->operand), $test->form === NullTestForm::IsNull);
        }
        Check::input($test instanceof Binary, 'A checked trigger mutation filter needs equality or a NULL test.');
        $operator = $test->operator;
        if ($operator === BinaryOperator::Equal || $operator === BinaryOperator::DoubleEqual) {
            $left = $this->field($test->left);
            $right = $this->field($test->right);
            if ($left instanceof TriggerTargetField && $right instanceof TriggerField) {
                return new TriggerEquality($left, $right, true);
            }
            Check::input($right instanceof TriggerTargetField && $left instanceof TriggerField, 'A trigger equality requires one local field and one row-image field.');

            return new TriggerEquality($right, $left, false);
        }
        Check::input(
            in_array($operator, [BinaryOperator::Is, BinaryOperator::IsNotDistinctFrom, BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom], true),
            'A checked trigger mutation filter needs equality or a NULL test.'
        );
        $isNull = $operator === BinaryOperator::Is || $operator === BinaryOperator::IsNotDistinctFrom;
        $left = $images->ungroup($test->left);
        $right = $images->ungroup($test->right);
        Check::input($left instanceof ParsedNull || $right instanceof ParsedNull, 'A checked trigger mutation filter needs equality or a NULL test.');

        return $this->nullTest($this->field($left instanceof ParsedNull ? $right : $left), $isNull);
    }

    /**
     * Keeps the resolved scope of a NULL test in its concrete type.
     */
    public function nullTest(TriggerField|TriggerTargetField $field, bool $isNull): TriggerCondition|TriggerTargetNullTest
    {
        return $field instanceof TriggerField ? new TriggerCondition($field, $isNull) : new TriggerTargetNullTest($field, $isNull);
    }
}
