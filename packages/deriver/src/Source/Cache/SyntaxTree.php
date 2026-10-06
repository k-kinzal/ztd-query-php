<?php

declare(strict_types=1);

namespace Deriver\Source\Cache;

use Deriver\Exception\InvalidInputException;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;

/**
 * Parsed declarations and source-relative diagnostics, independent of any snapshot.
 * @visibility root
 */
final class SyntaxTree
{
    /**
     * @param list<Stmt> $nodes Resolved source statements
     * @param list<array{message: string, line: int}> $errors Syntax or name-resolution diagnostics
     * @param int $size Number of raw syntax nodes
     * @param int $depth Maximum raw syntax depth
     */
    public function __construct(public readonly array $nodes, public readonly array $errors = [], public readonly int $size = 0, public readonly int $depth = 0)
    {
    }

    /**
     * Returns private AST nodes for the receiving declaration index.
     * @return self Independently mutable parser nodes
     * @throws InvalidInputException If the parser violates its top-level node contract
     */
    public function copy(): self
    {
        $nodes = [];
        foreach ((new NodeTraverser(new CloningVisitor()))->traverse($this->nodes) as $node) {
            if (!$node instanceof Stmt) {
                throw new InvalidInputException('The parser returned an invalid top-level node.');
            }
            $nodes[] = $node;
        }
        return new self($nodes, $this->errors, $this->size, $this->depth);
    }
}
