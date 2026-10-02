<?php

declare(strict_types=1);

namespace SqlSemantics\Rendering;

use SqlSemantics\Contract\Codec;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * The typed, temporary output a statement structure is written to.
 *
 * Nodes add keywords, punctuation, names and literal spellings; there is no
 * operation that adds a free SQL fragment. The pieces are joined into text by
 * the lexical writer and the result is parsed again and compared with the
 * structure before an operation is published.
 *
 * @visibility SqlSemantics
 */
final class Output
{
    /**
     * @var list<Piece>
     */
    private array $pieces = [];

    private bool $glue = false;

    /**
     * @param Codec $codec The name codec of the language profile
     */
    public function __construct(private readonly Codec $codec)
    {
    }

    /**
     * Writes fixed keywords.
     *
     * @return $this
     */
    public function keyword(string ...$words): self
    {
        foreach ($words as $word) {
            Check::invariant(preg_match('/\A[A-Z][A-Z0-9_]*\z/', $word) === 1, 'A keyword piece is one upper-case word.');
            $this->add(PieceKind::Keyword, $word);
        }

        return $this;
    }

    /**
     * Writes punctuation or an operator made of symbol characters only.
     *
     * @return $this
     */
    public function symbol(string $symbol): self
    {
        Check::invariant(preg_match('/\A[][(){}.,;:=<>!+*\/%^|&~@#?$-]+\z/', $symbol) === 1, 'A symbol piece contains punctuation only.');
        $this->add(PieceKind::Symbol, $symbol);

        return $this;
    }

    /**
     * Writes a decoded name, spelled by the profile codec for its position.
     *
     * @return $this
     */
    public function name(Name $name, NameUse $use = NameUse::Column): self
    {
        $this->add(PieceKind::Name, $this->codec->name($name, $use));

        return $this;
    }

    /**
     * Writes the spelling a literal or parameter value produces from its exact value.
     *
     * @return $this
     */
    public function spelled(string $text): self
    {
        Check::invariant($text !== '', 'A spelled piece is not empty.');
        $this->add(PieceKind::Literal, $text);

        return $this;
    }

    /**
     * Joins the next piece to the previous one without a separating space.
     *
     * @return $this
     */
    public function glue(): self
    {
        $this->glue = true;

        return $this;
    }

    /**
     * Writes a child node, when there is one.
     *
     * @return $this
     */
    public function node(?Node $node): self
    {
        $node?->render($this);

        return $this;
    }

    /**
     * Writes child nodes separated by a symbol.
     *
     * @param list<Node> $nodes
     * @return $this
     */
    public function list(array $nodes, string $separator = ','): self
    {
        foreach ($nodes as $position => $node) {
            if ($position > 0) {
                $this->symbol($separator);
            }
            $node->render($this);
        }

        return $this;
    }

    /**
     * Adds one piece, consuming a pending glue request.
     */
    public function add(PieceKind $kind, string $text): void
    {
        $this->pieces[] = new Piece($kind, $text, $this->glue);
        $this->glue = false;
    }

    /**
     * Answers the pieces written so far.
     *
     * @return list<Piece>
     */
    public function pieces(): array
    {
        return $this->pieces;
    }
}
