<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One argument of a call written as `name(...)`, with the alias a loadable function receives as the name of the argument.
 *
 * Without an alias a loadable function sees the text of the argument as its
 * name; native functions reject an alias. The call that holds the argument
 * derives its expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/udf-arguments.html.
 *
 * @visibility public
 * @example Reading an argument and its alias
 *     $argument = new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Statement\Identifier\Name('total'));
 *     [$argument->expression->name->value, $argument->alias?->value] // => ['a', 'total']
 */
final class CallArgument implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The argument expression
     * @param Name|null $alias The alias written after it
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null)
    {
    }

    /**
     * Writes the expression and its alias.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
