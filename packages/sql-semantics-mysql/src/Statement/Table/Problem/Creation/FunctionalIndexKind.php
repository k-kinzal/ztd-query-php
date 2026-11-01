<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A retained primary, full-text or spatial key supplies a functional operand.
 *
 * The index kind cannot store this operand independently of catalog state.
 * Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Reading the resolved functional-key condition
 *     $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Spatial, $part))->message() // => 'A SPATIAL index cannot have a functional key part.'
 */
final class FunctionalIndexKind implements Diagnostic
{
    use Snapshot;

    /**
     * @param IndexKind $kind The kind of the complete retained key
     * @param KeyPart $owner The functional operand that identifies its declaration
     */
    public function __construct(public readonly IndexKind $kind, public readonly KeyPart $owner)
    {
        Check::input(in_array($kind, [IndexKind::Primary, IndexKind::FullText, IndexKind::Spatial], true), 'A functional-key kind condition belongs to a primary, full-text or spatial index.');
        Check::input(!$owner instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart, 'A functional-key kind condition retains an expression operand.');
    }

    /**
     * Describes the incompatible index and operand roles.
     */
    public function message(): string
    {
        return 'A ' . $this->kind->value . ' index cannot have a functional key part.';
    }
}
