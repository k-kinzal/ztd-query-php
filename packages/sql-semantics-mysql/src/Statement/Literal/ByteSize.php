<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A size in bytes: a plain number, or a word such as `16M` that the lexer reads as an identifier.
 *
 * A number followed at once by a letter is one identifier token; the server
 * reads the letters K, M and G as multipliers and rejects any other word.
 * The word is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility SqlSemantics
 * @example Holding a size written with a multiplier
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('16M')))->word?->value // => '16M'
 */
final class ByteSize implements Node
{
    use Snapshot;

    /**
     * @param Numeral|null $number The size written as a number
     * @param Name|null $word The size written as a word; exactly one of the two is given
     */
    public function __construct(public readonly ?Numeral $number, public readonly ?Name $word = null)
    {
        Check::input(($number === null) !== ($word === null), 'A size is either a number or a word.');
    }

    /**
     * Writes the number or the word.
     */
    public function render(Output $out): void
    {
        if ($this->word !== null) {
            $out->name($this->word, NameUse::Label);
        }
        $out->node($this->number);
    }
}
