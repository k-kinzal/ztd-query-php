<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Keywords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A reserved keyword, or NONE, written as the value of a definition option.
 *
 * A definition accepts any reserved keyword as a value and passes its
 * lower-case text to the command, as in `CREATE OPERATOR ... (commutator = none)`
 * or `(analyze = true)`.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html.
 *
 * @visibility public
 * @example Reading the text of a keyword value
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord(new \SqlSemantics\Statement\Identifier\Name('none')))->word->value // => 'none'
 * @example Rejecting a word that is not a reserved keyword
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord(new \SqlSemantics\Statement\Identifier\Name('fillfactor')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class KeywordWord implements OptionArgument
{
    use Snapshot;

    /**
     * @param Name $word The keyword in lower case
     */
    public function __construct(public readonly Name $word)
    {
        $reserved = $word->value === 'none';
        foreach ([GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172] as $release) {
            $reserved = $reserved || in_array('reserved_keyword', (new Keywords($release))->categories($word->value), true);
        }
        Check::input($reserved, 'A keyword value is a reserved keyword or none.');
    }

    /**
     * Derives nothing: a keyword holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword(strtoupper($this->word->value));
    }
}
