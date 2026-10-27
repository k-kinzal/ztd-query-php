<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A size in bytes: a plain number, or a word such as `16M` that the lexer reads as an identifier.
 *
 * A number followed at once by a letter is one identifier token; the server
 * reads the letters K, M and G as multipliers and rejects any other word.
 * The word is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding a size written with a multiplier
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Statement\Identifier\Name('16M')))->word?->value // => '16M'
 * @example Refusing an unknown multiplier
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Statement\Identifier\Name('3T')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an overflowing coefficient
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Statement\Identifier\Name('2147483648K')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fractional byte count
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ByteSize implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Numeral|null $number The size written as a number
     * @param Name|null $word The size written as a word; exactly one of the two is given
     */
    public function __construct(public readonly ?Numeral $number, public readonly ?Name $word = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(($number === null) !== ($word === null), 'A size is either a number or a word.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Server\StorageSizes())->problem($number, $word) === null, 'A storage size is an integer token or a bounded K, M or G coefficient.');
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
