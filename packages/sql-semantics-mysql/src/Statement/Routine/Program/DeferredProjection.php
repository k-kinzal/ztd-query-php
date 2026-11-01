<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A stored projection that requires invocation-time operand preparation.
 *
 * It retains the complete row or computation and its alias. It is not a
 * SelectExpression: an ordinary projected expression supplies one scalar.
 *
 * @visibility public
 * @example Retaining a row without presenting it as a scalar expression
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]));
 *     $item->expression instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredProjection implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $expression The stored projection operand
     * @param Name|null $alias The optional result alias
     * @param AliasMark $mark Whether the alias follows AS
     */
    public function __construct(public readonly RowOperand|DeferredOperand $expression, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($mark !== AliasMark::Equals && ($alias !== null || $mark === AliasMark::As), 'A stored select alias follows AS or is bare; an absent alias has no mark.');
    }

    /**
     * Writes the retained operand and alias.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
