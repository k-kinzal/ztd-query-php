<?php

declare(strict_types=1);

namespace SqlSemantics\Rendering;

use SqlSemantics\Contract\Codec;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Spelling\Layout;

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

    private bool $spelling = false;

    private ?string $trail = null;

    /**
     * @var list<Piece>
     */
    private array $canonical = [];

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
     * Writes one block comment as the trivia between the previous piece and the next one.
     *
     * A dialect whose server reads a comment, such as an optimizer hint comment, writes it
     * here; the comment separates the pieces as a space does and adds no token.
     *
     * @return $this
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the text is not exactly one block comment
     */
    public function comment(string $text): self
    {
        Check::invariant(preg_match('~\A/\*.*\*/\z~s', $text) === 1 && strpos($text, '*/') === strlen($text) - 2, 'A comment is one block comment.');
        $this->trail = ' ' . $text . ' ';

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
     * Writes a node in the spelling of a layout, or regularly when there is none.
     *
     * The node renders its pieces as always; each piece then takes the
     * spelling and the preceding trivia of the layout token at its position,
     * and the next piece written takes the trailing trivia of the layout.
     * Inside a spelled region a nested layout has no effect, since the outer
     * layout spells every token of the region.
     *
     * @return $this
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the layout does not have one token per rendered piece
     */
    public function layout(?Layout $layout, Node $node): self
    {
        if ($layout === null || $this->spelling) {
            $node->render($this);

            return $this;
        }
        $start = count($this->pieces);
        $this->spelling = true;
        $node->render($this);
        $this->spelling = false;
        $count = count($this->pieces) - $start;
        Check::invariant($count === count($layout->tokens), 'The layout spells ' . count($layout->tokens) . ' tokens where the rendering writes ' . $count . '.');
        $spelled = [];
        foreach (array_slice($this->pieces, $start) as $offset => $canonical) {
            $token = $layout->tokens[$offset];
            $spelled[] = new Piece($canonical->kind, $token->text, $canonical->glued, $offset === 0 ? $canonical->gap : $token->gap);
        }
        $this->pieces = [...array_slice($this->pieces, 0, $start), ...$spelled];
        $this->trail = $layout->trail === '' ? null : $layout->trail;

        return $this;
    }

    /**
     * Answers the trivia a layout wrote after the last piece, when nothing followed it.
     */
    public function trailing(): string
    {
        return $this->trail ?? '';
    }

    /**
     * Answers the pieces as the structure renders them, before any layout re-spelled them, for validation.
     *
     * @return list<Piece>
     */
    public function canonical(): array
    {
        return $this->canonical;
    }

    /**
     * Adds one piece, consuming a pending glue request.
     */
    public function add(PieceKind $kind, string $text): void
    {
        $this->pieces[] = new Piece($kind, $text, $this->glue, $this->trail);
        $this->canonical[] = new Piece($kind, $text, $this->glue);
        $this->glue = false;
        $this->trail = null;
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
