<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Syntax;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Tokens and grammar-derived layout marks, indexed in source order.
 *
 * @visibility SqlFormatter
 */
final class Document
{
    /**
     * @var list<Token>
     */
    public array $tokens = [];
    /**
     * @var array<int, int> Clause start to last keyword token
     */
    public array $clauses = [];
    /**
     * @var array<int, true>
     */
    public array $commas = [];
    /**
     * @var array<int, true>
     */
    public array $logical = [];
    /**
     * @var array<int, true>
     */
    public array $unary = [];
    /**
     * @var array<int, int> Parenthesis pairs
     */
    public array $pairs = [];
    /**
     * @var array<int, true>
     */
    public array $blocks = [];
    /**
     * @var array<int, int>
     */
    public array $casePairs = [];
    /**
     * @var array<int, true>
     */
    public array $caseBranches = [];

    /**
     * Retains trivia following the root node.
     */
    public function __construct(public readonly string $trailing)
    {
    }

    /**
     * Collects tokens and determines grammar and nesting boundaries.
     */
    public static function from(Node $tree, Rules $rules): self
    {
        $document = new self($tree->trailing);
        (new Analyzer($document, $rules))->visit($tree);
        Brackets::mark($document);
        return $document;
    }

}
