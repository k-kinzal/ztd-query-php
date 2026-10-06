<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;

/**
 * Lowers identifier tokens at the positions where SQLite reads the written text.
 *
 * Rule: SQLITE-WORD-LOWER-001. Scope: the name of a table option, the word
 * after a generated column expression and the identifier after DEFAULT.
 * Constructor: Word, holding the decoded name, which is the recorded operand
 * leaf, and the quoting the first character of the token shows. Terminates:
 * one token. Source: https://sqlite.org/lang_keywords.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class WordRule
{
    /**
     * The quoting each opening character stands for.
     */
    private const QUOTES = ["'" => WordQuote::Single, '"' => WordQuote::Double, '`' => WordQuote::Backtick, '[' => WordQuote::Bracket];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one identifier or string token to a word with its quoting.
     */
    public function word(Token $token): Word
    {
        return new Word($this->lowering->names->token($token), self::QUOTES[$token->text[0] ?? ''] ?? WordQuote::Bare);
    }

    /**
     * Lowers a name position (`nm`, one identifier or string token) to a word with its quoting.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): Word
    {
        $form = $this->lowering->productions->form($name);
        if ($name->name !== 'nm') {
            throw ImplementationGap::production($form);
        }

        return $this->word($form->token(0));
    }
}
