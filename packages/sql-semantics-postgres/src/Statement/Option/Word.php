<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An identifier or non-reserved keyword written as an option or parameter value.
 *
 * The server receives the decoded word as text, so `SET search_path TO Public`
 * passes `public`.
 * Source: https://www.postgresql.org/docs/17/sql-set.html.
 *
 * @visibility public
 * @example Reading the text of a word value
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Option\Word(new \SqlSemantics\Statement\Identifier\Name('public')))->word->value // => 'public'
 */
final class Word implements OptionArgument
{
    use Snapshot;

    /**
     * @param Name $word The decoded word
     */
    public function __construct(public readonly Name $word)
    {
    }

    /**
     * Derives nothing: a word holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the word where the grammar reads a non-reserved word.
     */
    public function render(Output $out): void
    {
        $out->name($this->word, NameUse::Column);
    }
}
