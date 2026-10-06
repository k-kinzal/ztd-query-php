<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of the parenthesized COPY option list: a name and an optional argument.
 *
 * Mirrors the `DefElem` of a COPY option. The argument is a word, a string,
 * a number, a boolean, `*`, DEFAULT (PostgreSQL 17) or a parenthesized list.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility public
 * @example Reading the options of COPY
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COPY t TO STDOUT (FORMAT csv, FORCE_QUOTE *, HEADER)");
 *     [$copy->statement->options[0]->name->value, $copy->statement->options[2]->argument, $copy->toString()] // => ['format', null, 'COPY t TO STDOUT (format csv, force_quote *, header)']
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
