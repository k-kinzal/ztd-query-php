<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Statement\Element;

/**
 * Lowers transient parser nodes into typed SQL arguments and finite options.
 *
 * @phpstan-type Recipe array{forward: int}|array{constant: string}|array{class: class-string<Element>, fields: list<int>}
 * @visibility SqlSemantics
 */
final class ValueReader
{
    /**
     * @param array<string, array<int, Recipe>> $recipes Complete construction vocabulary
     */
    public function __construct(private readonly array $recipes)
    {
    }

    /**
     * Loads the construction vocabulary supplied by a database package.
     *
     * @throws LogicException When generated resources are missing or invalid
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new LogicException('Missing statement model resource: ' . $path);
        }
        $reader = require $path;
        if (!$reader instanceof self) {
            throw new LogicException('Invalid statement model resource: ' . $path);
        }

        return $reader;
    }

    /**
     * Discards the input node after assigning its values to their named fields.
     *
     * @throws LogicException When parser and model resources disagree
     */
    public function read(Node $node): Element
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

            return $this->read($child);
        }
        if (isset($recipe['constant'])) {
            $choice = constant($recipe['constant']);
            if (!$choice instanceof Element) {
                throw new LogicException('A model choice must implement Element');
            }

            return $choice;
        }
        $arguments = [];
        foreach ($recipe['fields'] as $index) {
            $child = $node->children[$index] ?? null;
            if ($child === null) {
                throw new LogicException('Missing model argument ' . $index . ' in ' . $node->name);
            }
            $arguments[] = $child instanceof Token ? $child->text : $this->read($child);
        }

        return new ($recipe['class'])(...$arguments);
    }
}
