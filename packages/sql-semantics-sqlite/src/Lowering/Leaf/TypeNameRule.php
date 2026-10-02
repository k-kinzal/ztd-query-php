<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;

/**
 * Lowers type names and signed numbers.
 *
 * Rule: SQLITE-TYPE-NAME-LOWER-001. Scope: typetoken, typename, signed,
 * plus_num, minus_num. A type name is its words in order, each with
 * its quoting, and its numeric arguments with their written signs. Terminates: the word list is flattened
 * iteratively. Source: https://sqlite.org/syntax/type-name.html,
 * https://sqlite.org/syntax/signed-number.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TypeNameRule
{
    /**
     * The quoting a word has by its first character.
     */
    private const QUOTES = ["'" => WordQuote::Single, '"' => WordQuote::Double, '`' => WordQuote::Backtick, '[' => WordQuote::Bracket];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `typetoken`: the type name, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function named(Node $typetoken): ?TypeName
    {
        $form = $this->lowering->productions->form($typetoken);

        return match ($form->signature) {
            'typetoken:' => null,
            'typetoken: typename' => new TypeName($this->words($form->node(0))),
            'typetoken: typename LP signed RP' => new TypeName($this->words($form->node(0)), [$this->signed($form->node(2))]),
            'typetoken: typename LP signed COMMA signed RP' => new TypeName($this->words($form->node(0)), [$this->signed($form->node(2)), $this->signed($form->node(4))]),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `typename` into its words in written order.
     *
     * @return list<Word>
     * @throws ImplementationGap When the production has no rule
     */
    public function words(Node $typename): array
    {
        $form = $this->lowering->productions->form($typename);
        if ($form->signature !== 'typename: ids' && $form->signature !== 'typename: typename ids') {
            throw ImplementationGap::production($form);
        }
        $words = [];
        foreach ((new Lists())->elements($typename) as $word) {
            if ($word instanceof Token) {
                $words[] = new Word($this->lowering->names->token($word), self::QUOTES[$word->text[0] ?? ''] ?? WordQuote::Bare);
            }
        }

        return $words;
    }

    /**
     * Lowers a `signed` number.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function signed(Node $signed): SignedNumber
    {
        $form = $this->lowering->productions->form($signed);

        return match ($form->signature) {
            'signed: plus_num' => $this->plusNumber($form->node(0)),
            'signed: minus_num' => $this->minusNumber($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `plus_num`: a number that is unsigned or written with a plus sign.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function plusNumber(Node $number): SignedNumber
    {
        $form = $this->lowering->productions->form($number);

        return match ($form->signature) {
            'plus_num: number' => new SignedNumber(null, $this->lowering->literals->number($form->token(0))),
            'plus_num: PLUS number' => new SignedNumber(NumberSign::Plus, $this->lowering->literals->number($form->token(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `minus_num`: a number written with a minus sign.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function minusNumber(Node $number): SignedNumber
    {
        $form = $this->lowering->productions->form($number);

        return match ($form->signature) {
            'minus_num: MINUS number' => new SignedNumber(NumberSign::Minus, $this->lowering->literals->number($form->token(1))),
            default => throw ImplementationGap::production($form),
        };
    }
}
