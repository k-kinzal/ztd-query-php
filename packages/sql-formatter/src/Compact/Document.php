<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Builds a canonical token stream without changing the parser's immutable tree.
 *
 * @visibility SqlFormatter
 */
final class Document
{
    /**
     * @var list<Token> Canonical tokens in source order
     */
    public readonly array $tokens;
    /**
     * Retained directives after the last SQL token.
     */
    public readonly string $trailing;
    /**
     * @var list<list<int>> Grammar-owned reduction candidates
     */
    public readonly array $candidates;
    private readonly ?string $shape;

    /**
     * Preserves directive ownership before applying grammar-specific reductions.
     */
    public function __construct(Node $tree, bool $mysql, bool $postgres)
    {
        $trivia = new Trivia($postgres, $mysql);
        $cleaned = [];
        $protected = [];
        $verbatim = [];
        $trailing = '';
        foreach ($tree->tokens() as $token) {
            $leading = $trivia->clean($token->leading);
            if ($trivia->executable) {
                $verbatim[$token->offset] = true;
            }
            if ($trivia->executable || $leading !== '') {
                $protected[$token->offset] = true;
            }
            if ($token->text === '') {
                $trailing .= $leading;
            }
            $cleaned[$token->offset] = new Token($token->symbol, $token->name, $token->text, $token->offset, $leading);
        }
        $this->trailing = $trailing . $trivia->clean($tree->trailing);
        $tokens = (new Visitor($cleaned, $protected, $verbatim, $mysql))->tokens($tree);
        $last = $tokens[count($tokens) - 1] ?? null;
        while ($last?->text === ';' && $this->trailing === '' && !isset($protected[$last->offset])) {
            array_pop($tokens);
            $last = $tokens[count($tokens) - 1] ?? null;
        }
        $this->tokens = $tokens;
        $this->candidates = Reductions::candidates($tree, $protected);
        $grouping = array_fill_keys(array_merge([], ...$this->candidates), true);
        $this->shape = (new Shape(array_column($tokens, null, 'offset'), $grouping))->of($tree);
    }

    /**
     * Verifies canonical terminal kinds, spellings, and retained directives.
     */
    public function signature(): string
    {
        return serialize([$this->shape, $this->trailing]);
    }
}
