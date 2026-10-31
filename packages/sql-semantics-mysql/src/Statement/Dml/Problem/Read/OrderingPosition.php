<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An output position requested by a write that has no select list.
 *
 * @visibility public
 * @example Describing an ordering position in a retained write
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('01')))->message() // => "Unknown column '01' in 'order clause'"
 */
final class OrderingPosition implements Diagnostic
{
    use Snapshot;

    /**
     * @param NumberLiteral|BooleanLiteral $position The integer position, including legacy boolean positions
     */
    public function __construct(public readonly NumberLiteral|BooleanLiteral $position)
    {
        Check::input(!$position instanceof NumberLiteral || $position->form === NumberForm::Integer, 'A write ordering position is an integer literal or a legacy boolean.');
    }

    /**
     * Describes the missing position in the server's words.
     */
    public function message(): string
    {
        $name = $this->position instanceof NumberLiteral ? $this->position->text : ($this->position->value ? 'TRUE' : 'FALSE');
        return "Unknown column '" . $name . "' in 'order clause'";
    }
}
