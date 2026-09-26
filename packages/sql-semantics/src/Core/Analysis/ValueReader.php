<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Statement\Assertion;
use SqlSemantics\Statement\Comments;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Statement;

/**
 * Lowers transient parser nodes into typed SQL arguments and finite options.
 *
 * A comment is kept by the outermost value whose symbol begins with the token
 * the comment was written before. Comments before the first token and after
 * the last one belong to the statement.
 *
 * @phpstan-type Recipe array{forward: int}|array{constant: string}|array{class: class-string<Element>, fields: list<int>}
 * @visibility SqlSemantics
 */
final class ValueReader
{
    use Assertion;

    private readonly Comments $none;

    /**
     * @param array<string, array<int, Recipe>> $recipes Complete construction vocabulary
     * @param TriviaReader $trivia Reads the comments of the language
     */
    public function __construct(private readonly array $recipes, private readonly TriviaReader $trivia = new TriviaReader())
    {
        $this->none = new Comments();
    }

    /**
     * Loads the construction vocabulary supplied by a database package.
     *
     * @param TriviaReader|null $trivia Reads the comments of the language, or null to read them as plain block and line comments
     *
     * @throws LogicException When generated resources are missing or invalid
     */
    public static function fromFile(string $path, ?TriviaReader $trivia = null): self
    {
        if (!is_file($path)) {
            throw new LogicException('Missing statement model resource: ' . $path);
        }
        $reader = require $path;
        if (!$reader instanceof self) {
            throw new LogicException('Invalid statement model resource: ' . $path);
        }

        return $trivia === null ? $reader : new self($reader->recipes, $trivia);
    }

    /**
     * Lowers a complete parse tree into a statement that keeps its comments, and discards the tree.
     *
     * @throws LogicException When parser and model resources disagree
     */
    public function statement(Node $root): Statement
    {
        $comments = $this->trivia->read($root);
        $command = $this->lower($root, $comments, true);
        $this->assertCompleteCommand($command);
        $around = [];
        if ($comments->leading !== []) {
            $around[Statement::BEFORE] = $comments->leading;
        }
        if ($comments->trailing !== []) {
            $around[Statement::AFTER] = $comments->trailing;
        }

        return new Statement($command, $around === [] ? $this->none : new Comments($around));
    }

    /**
     * Discards the input node after assigning its values to their named fields.
     *
     * Comments before the first token and after the last one are not kept; use
     * statement() for a complete tree.
     *
     * @throws LogicException When parser and model resources disagree
     */
    public function read(Node $node): Element
    {
        return $this->lower($node, $this->trivia->read($node), true);
    }

    /**
     * Builds the value of a node, keeping the comments of the symbols it owns.
     *
     * @param SourceComments $comments The comments of the tree the node belongs to
     * @param bool $firstKept Whether an enclosing value or the statement already keeps the comments before the first token
     *
     * @throws LogicException When parser and model resources disagree
     */
    public function lower(Node $node, SourceComments $comments, bool $firstKept): Element
    {
        $recipe = $this->recipes[$node->name][$node->ordinal] ?? null;
        if ($recipe === null) {
            throw new LogicException('Parser/model resource mismatch at ' . $node->name . ':' . $node->ordinal);
        }
        if (isset($recipe['forward'])) {
            $child = $node->children[$recipe['forward']] ?? null;
            if (!$child instanceof Node) {
                throw new LogicException('Forwarding model requires a structured value');
            }

            return $this->lower($child, $comments, $firstKept);
        }
        if (isset($recipe['constant'])) {
            $choice = constant($recipe['constant']);
            if (!$choice instanceof Element) {
                throw new LogicException('A model choice must implement Element');
            }

            return $choice;
        }
        $positions = [];
        $begins = [];
        foreach ($node->children as $index => $child) {
            $token = $child instanceof Token ? $child : $comments->first($child);
            if ($token === null) {
                continue;
            }
            $begins[$index] = true;
            if ($firstKept) {
                $firstKept = false;
                continue;
            }
            $texts = $comments->before($token);
            if ($texts !== []) {
                $positions[$index] = $texts;
            }
        }
        $arguments = [];
        foreach ($recipe['fields'] as $index) {
            $child = $node->children[$index] ?? null;
            if ($child === null) {
                throw new LogicException('Missing model argument ' . $index . ' in ' . $node->name);
            }
            $arguments[] = $child instanceof Token ? $child->text : $this->lower($child, $comments, isset($begins[$index]));
        }

        return new ($recipe['class'])(...$arguments, comments: $positions === [] ? $this->none : new Comments($positions));
    }
}
