<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;

/**
 * One argument of a call written as `name(...)`, with the alias a loadable function receives as the name of the argument.
 *
 * Without an alias a loadable function sees the text of the argument as its
 * name; native functions reject an alias. The call that holds the argument
 * derives its expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/udf-arguments.html.
 *
 * @visibility SqlSemantics
 * @example Reading an argument and its alias
 *     $argument = new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('total'));
 *     [$argument->expression->name->value, $argument->alias?->value] // => ['a', 'total']
 */
final class CallArgument implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The argument expression
     * @param Name|null $alias The alias written after it
     * @param AliasMark $mark Whether the alias is written after AS
     * @throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction When the mark does not fit the alias
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As)
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
