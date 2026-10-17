<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored call argument whose scalar requirement is deferred to invocation.
 *
 * The expression retains its row or deferred-computation role. Argument aliases
 * retain their own spelling and are checked by the enclosing call's signature.
 *
 * @visibility public
 * @example Retaining a row argument with an alias
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument($row, new \SqlSemantics\Statement\Identifier\Name('pair')))->alias->value // => 'pair'
 */
final class DeferredCallArgument implements Node
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $expression The argument expression
     * @param Name|null $alias The alias written after it
     * @param AliasMark $mark Whether the alias is written after AS
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the mark does not fit the alias
     */
    public function __construct(public readonly RowOperand|DeferredOperand $expression, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($mark !== AliasMark::Equals && ($alias !== null || $mark === AliasMark::As), 'An argument alias is written with or without AS, and an argument without alias has no alias mark.');
    }

    /**
     * Writes the expression and its alias.
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
