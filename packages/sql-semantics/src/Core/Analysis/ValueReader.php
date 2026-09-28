<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Comments;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Equality;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Syntax;
use WeakMap;

/**
 * Lowers transient parser nodes into typed SQL arguments and finite options.
 *
 * A comment is kept by the outermost value whose symbol begins with the token
 * the comment was written before. Comments before the first token and after
 * the last one belong to the statement.
 *
 * @visibility SqlSemantics
 */
final class ValueReader
{
    private readonly Comments $none;

    /**
     * The value each node of a tree still in use was lowered into, so a node read again answers the same value.
     *
     * @var WeakMap<Node, Element>
     */
    private WeakMap $lowered;

    /**
     * @param Vocabulary $vocabulary Complete construction vocabulary of the release
     * @param TriviaReader $trivia Reads the comments of the language
     */
    public function __construct(public readonly Vocabulary $vocabulary, private readonly TriviaReader $trivia = new TriviaReader())
    {
        $this->none = new Comments();
        $this->lowered = new WeakMap();
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
        return new self(Vocabulary::fromFile($path), $trivia ?? new TriviaReader());
    }

    /**
     * Lowers a complete parse tree into a statement of the language that keeps its comments, and discards the tree.
     *
     * @throws LogicException When parser and model resources disagree
     * @throws \SqlSemantics\Statement\StatementException When the lowered statement does not read back as itself in the language
     */
    public function statement(Node $root, Syntax $syntax): Statement
    {
        [$command, $comments] = $this->command($root);

        return new Statement($syntax, $command, $comments);
    }

    /**
     * Lowers a complete parse tree into its command and the comments written around it, and discards the tree.
     *
     * @return array{Command, Comments}
     * @throws LogicException When parser and model resources disagree
     */
    public function command(Node $root): array
    {
        $comments = $this->trivia->read($root);
        $command = $this->lower($root, $comments, true);
        if (!$command instanceof Command) {
            throw new LogicException('A statement root must be a complete SQL command or command sequence, ' . $command::class . ' given.');
        }
        $around = [];
        if ($comments->leading !== []) {
            $around[Statement::BEFORE] = $comments->leading;
        }
        if ($comments->trailing !== []) {
            $around[Statement::AFTER] = $comments->trailing;
        }

        return [$command, $around === [] ? $this->none : new Comments($around)];
    }

    /**
     * Makes the nodes of a tree of a command's own SQL answer the values of that command.
     *
     * The tree is lowered, and each value lowered is matched, form by form,
     * with the value of the command at the same place, so reading a node of
     * the tree answers the value the command already holds. A command built
     * without the envelope of the grammar's start form is matched inside it.
     *
     * @throws LogicException When the tree is not of the command's SQL
     */
    public function adopt(Node $root, Command $command): void
    {
        $read = Equality::enclosed($this->command($root)[0], $command);
        if ($read === null) {
            throw new LogicException('The tree is not of the SQL of the command it adopts.');
        }
        $matched = [];
        $match = static function (Element $own, Element $copy) use (&$match, &$matched): void {
            $matched[spl_object_id($copy)] = $own;
            $copies = $copy->children();
            foreach ($own->children() as $index => $child) {
                $match($child, $copies[$index]);
            }
        };
        $match($command, $read);
        $adopt = function (Node $node) use (&$adopt, $matched): void {
            $value = $this->lowered[$node] ?? null;
            if ($value !== null && isset($matched[spl_object_id($value)])) {
                $this->lowered[$node] = $matched[spl_object_id($value)];
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node) {
                    $adopt($child);
                }
            }
        };
        $adopt($root);
    }

    /**
     * Reads the comments of a complete parse tree as the language reads them.
     */
    public function comments(Node $root): SourceComments
    {
        return $this->trivia->read($root);
    }

    /**
     * Answers the value of a node: the one its tree was lowered into, or a value lowered from the node alone.
     *
     * A node of a tree lowered by command() answers the value the command
     * holds, so what is read from the tree, such as a declared column, is the
     * value of the statement itself and can be found and replaced by
     * identity. Comments before the first token and after the last one are
     * not kept; use statement() for a complete tree.
     *
     * @throws LogicException When parser and model resources disagree
     */
    public function read(Node $node): Element
    {
        return $this->lowered[$node] ?? $this->lower($node, $this->trivia->read($node), true);
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
        $recipe = $this->vocabulary->recipe($node->name, $node->ordinal);
        if ($recipe === null) {
            throw new LogicException('Parser/model resource mismatch at ' . $node->name . ':' . $node->ordinal);
        }
        if (isset($recipe['forward'])) {
            $child = $node->children[$recipe['forward']] ?? null;
            if (!$child instanceof Node) {
                throw new LogicException('Forwarding model requires a structured value');
            }

            return $this->lowered[$node] = $this->lower($child, $comments, $firstKept);
        }
        if (isset($recipe['constant'])) {
            $choice = constant($recipe['constant']);
            if (!$choice instanceof Element) {
                throw new LogicException('A model choice must implement Element');
            }

            return $this->lowered[$node] = $choice;
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

        return $this->lowered[$node] = new ($recipe['class'])(...$arguments, comments: $positions === [] ? $this->none : new Comments($positions));
    }
}
