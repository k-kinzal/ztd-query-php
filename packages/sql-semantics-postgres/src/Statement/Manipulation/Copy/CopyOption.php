<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

/**
 * One option of the parenthesized COPY option list: a name and an optional argument.
 *
 * Mirrors the `DefElem` of a COPY option. The argument is a word, a string,
 * a number, a boolean, `*`, DEFAULT (PostgreSQL 17) or a parenthesized list.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility SqlSemantics
 */
final class CopyOption implements Node
{
    use Snapshot;

    /**
     * @param Name $name The option name
     * @param OptionArgument|null $argument The argument: a boolean keyword, a word, a string, a number, DEFAULT, `*` or a list
     *
     * @throws InvalidConstruction When the argument is of another kind
     */
    public function __construct(public readonly Name $name, public readonly ?OptionArgument $argument = null)
    {
        Check::input(
            $argument === null || $argument instanceof Toggle || $argument instanceof Word || $argument instanceof StringConstant || $argument instanceof SignedNumber
                || $argument instanceof CopyAllColumns || $argument instanceof CopyArguments || ($argument instanceof KeywordWord && $argument->word->value === 'default'),
            'A COPY option takes a boolean keyword, a word, a string, a number, DEFAULT, * or a list.',
        );
    }

    /**
     * Writes the name and the argument.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label)->node($this->argument);
    }
}
