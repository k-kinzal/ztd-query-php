<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\SyntaxException;

/**
 * Reads a whole grammar file, as Lemon's `WAITING_FOR_DECL_OR_RULE` state does.
 *
 * A `%` starts a declaration, a nonterminal starts a rule, and a code block
 * or a `[TOKEN]` mark attaches to the rule most recently completed, even
 * when declarations come between.
 *
 * @visibility root
 */
final class GrammarReader
{
    /**
     * @param DeclarationReader $declarations Reads declarations
     * @param RuleReader $rules Reads rules
     */
    public function __construct(
        private readonly DeclarationReader $declarations = new DeclarationReader(),
        private readonly RuleReader $rules = new RuleReader(),
    ) {
    }

    /**
     * Reads every item of the file.
     *
     * @param TokenStream $tokens The tokens
     *
     * @return GrammarFile The tree
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function read(TokenStream $tokens): GrammarFile
    {
        $registry = new SymbolRegistry();
        $items = [];
        $previous = null;
        $index = -1;
        while (!$tokens->eof()) {
            $token = $tokens->next();
            if ($token->isPunctuation('%')) {
                $items[] = $this->declarations->read($tokens, $registry, $token->location);
                continue;
            }
            if ($token->isLowerWord()) {
                $previous = $this->rules->read($token, $tokens, $registry);
                $items[] = $previous;
                $index = count($items) - 1;
                continue;
            }
            if ($token->is(TokenKind::Code)) {
                $previous = $this->code($previous, $token);
            } elseif ($token->isPunctuation('[')) {
                $previous = $this->precedence($previous, $tokens, $registry);
            } else {
                throw new SyntaxException("Token \"{$token->raw}\" should be either \"%\" or a nonterminal name.", $token->location);
            }
            $items[$index] = $previous;
        }

        return new GrammarFile(array_values($items));
    }

    /**
     * Attaches a code block to the previous rule.
     *
     * @param Rule|null $previous The rule most recently completed
     * @param Token $code The code token
     *
     * @return Rule The rule with the code, or marked never to reduce
     *
     * @throws SyntaxException When there is no rule or it already has code
     */
    public function code(?Rule $previous, Token $code): Rule
    {
        if ($previous === null) {
            throw new SyntaxException('There is no prior rule upon which to attach the code fragment which begins on this line.', $code->location);
        }
        if ($previous->code !== null) {
            throw new SyntaxException('Code fragment beginning on this line is not the first to follow the previous rule.', $code->location);
        }
        if ($code->text === 'NEVER-REDUCE') {
            return $previous->withNeverReduce();
        }

        return $previous->withCode(new CodeBlock($code->text, $code->location));
    }

    /**
     * Reads `TOKEN]` after an opening bracket and marks the previous rule with it.
     *
     * @param Rule|null $previous The rule most recently completed
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return Rule The rule with the mark
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function precedence(?Rule $previous, TokenStream $tokens, SymbolRegistry $registry): Rule
    {
        $symbol = $tokens->next();
        if (!$symbol->isUpperWord()) {
            throw new SyntaxException('The precedence symbol must be a terminal.', $symbol->location);
        }
        if ($previous === null) {
            throw new SyntaxException("There is no prior rule to assign precedence \"[{$symbol->text}]\".", $symbol->location);
        }
        if ($previous->precedence !== null) {
            throw new SyntaxException('Precedence mark on this line is not the first to follow the previous rule.', $symbol->location);
        }
        $registry->see($symbol->text);
        $close = $tokens->next();
        if (!$close->isPunctuation(']')) {
            throw new SyntaxException('Missing "]" on precedence mark.', $close->location);
        }

        return $previous->withPrecedence(new Symbol($symbol->text, $symbol->location));
    }
}
