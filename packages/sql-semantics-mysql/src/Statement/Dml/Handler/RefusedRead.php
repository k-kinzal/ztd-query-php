<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete HANDLER read with a non-scalar operand or a stored-function interpretation.
 *
 * Handler, index, function and LIMIT dependencies remain available before operand preparation.
 * Refused expressions are not published as ordinary scalar values.
 *
 * @visibility public
 * @example Describing a refused handler predicate
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $read = new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler(new \SqlSemantics\Statement\Identifier\Name('h')), \SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::First, where: $row);
 *     $read->message() // => 'A HANDLER read retains a refused operand.'
 */
final class RefusedRead implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<Scalar|DefaultRequest|RowOperand|DeferredOperand> Complete key operands in written order
     */
    public readonly array $values;

    /**
     * @param OpenHandler $handler The session's open handler identity
     * @param ScanDirection|IndexDirection|KeyComparison $mode The scan, index movement or key comparison
     * @param Name|null $index The index required by index movement and key comparison
     * @param list<Scalar|DefaultRequest|RowOperand|DeferredOperand> $values Key comparison operands
     * @param Scalar|RowOperand|DeferredOperand|null $where The complete predicate
     * @param Limit|null $limit The read's row limit
     */
    public function __construct(public readonly OpenHandler $handler, public readonly ScanDirection|IndexDirection|KeyComparison $mode, public readonly ?Name $index = null, array $values = [], public readonly Scalar|RowOperand|DeferredOperand|null $where = null, public readonly ?Limit $limit = null)
    {
        $accepted = [];
        foreach (Check::listOf($values, Operand::class, 'Handler key operands are expressions or DEFAULT.') as $value) {
            Check::input($value instanceof Scalar || $value instanceof DefaultRequest || $value instanceof RowOperand || $value instanceof DeferredOperand, 'A handler key operand is scalar, DEFAULT or a retained refused expression.');
            $accepted[] = $value;
        }
        $this->values = $accepted;
        Check::input(($mode instanceof ScanDirection) === ($index === null), 'An indexed handler read names its index.');
        Check::input(($mode instanceof KeyComparison) === ($values !== []), 'A handler key comparison has at least one value; other reads have none.');
        $calls = (new \SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands())->releases([...$this->values, ...($where === null ? [] : [$where])]);
        Check::input($where instanceof RowOperand || $where instanceof DeferredOperand || array_filter($values, static fn ($value): bool => $value instanceof RowOperand || $value instanceof DeferredOperand) !== [] || $calls !== \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain::ALL, 'A refused handler read retains a non-scalar operand or a stored function.');
    }

    /**
     * Describes the retained operand role without deciding execution precedence.
     */
    public function message(): string
    {
        return 'A HANDLER read retains a refused operand.';
    }
}
