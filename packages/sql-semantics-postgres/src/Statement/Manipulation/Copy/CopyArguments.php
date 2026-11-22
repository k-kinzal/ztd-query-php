<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

/**
 * A parenthesized list of words or strings as the argument of a COPY option, such as the columns of `force_null`.
 *
 * Mirrors the `List` argument of a COPY `DefElem`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility SqlSemantics
 * @example Refusing an empty list
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyArguments([]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class CopyArguments implements OptionArgument
{
    use Snapshot;

    /**
     * @var non-empty-list<Toggle|Word|StringConstant> The items in written order
     */
    public readonly array $items;

    /**
     * @param list<OptionArgument> $items The items in written order: at least one word, string or boolean keyword
     *
     * @throws InvalidConstruction When there is no item, or an item is not a word, a string or a boolean keyword
     */
    public function __construct(array $items)
    {
        $this->items = (new ClosedList())->of($items, [Toggle::class, Word::class, StringConstant::class], 'A list argument holds at least one word, string or boolean keyword.', 1);
    }

    /**
     * Derives nothing: the items are words and strings.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the parenthesized items.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->items)->symbol(')');
    }
}
