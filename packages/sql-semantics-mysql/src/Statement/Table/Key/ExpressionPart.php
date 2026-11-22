<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;

/**
 * A functional key part: an expression in parentheses that the index stores (MySQL 8.0.13 and later).
 *
 * The expression sees the columns of the indexed table. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-functional-key-parts.
 *
 * @visibility SqlSemantics
 */
final class ExpressionPart implements KeyPart
{
    use Snapshot;

    /**
     * @param Scalar $expression The indexed expression
     * @param Direction|null $direction The sort direction, when written
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Direction $direction = null)
    {
    }

    /**
     * Derives the expression at the position of the indexed table.
     */
    public function deriveKeyPart(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->expression, $scope);
    }

    /**
     * Writes the parenthesized expression and the direction.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->expression)->symbol(')');
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
