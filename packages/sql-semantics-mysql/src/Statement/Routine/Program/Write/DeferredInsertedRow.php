<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A VALUES row template retaining at least one non-scalar value position.
 *
 * Ordinary InsertedRow keeps its scalar/default domain. This template belongs to
 * a stored INSERT or REPLACE, which retains all of its dependencies until invoked.
 *
 * @visibility public
 * @example Refusing an ordinary row in the deferred role
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredInsertedRow implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|DefaultRequest|RowOperand|DeferredOperand> Complete row operands
     */
    public readonly array $values;

    /**
     * @param list<Scalar|DefaultRequest|RowOperand|DeferredOperand> $values The complete row, with at least one retained condition
     */
    public function __construct(array $values)
    {
        $items = [];
        $deferred = false;
        foreach (Check::listOf($values, Operand::class, 'A deferred VALUES row has operands.', 1) as $value) {
            Check::input($value instanceof Scalar || $value instanceof DefaultRequest || $value instanceof RowOperand || $value instanceof DeferredOperand, 'A VALUES position retains its value or deferred role.');
            $items[] = $value;
            $deferred = $deferred || $value instanceof RowOperand || $value instanceof DeferredOperand;
        }
        Check::input($deferred, 'A deferred VALUES row retains a preparation condition.');
        $this->values = $items;
    }

    /**
     * Writes all positions without replacing deferred operands.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->values)->symbol(')');
    }
}
