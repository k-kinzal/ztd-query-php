<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use SqlCatalog\Php\ParsedFile;
use WeakMap;

/**
 * Where a node sits in the source: which statement list holds it, and which file.
 *
 * Walking back from a call means walking back through the statement lists it
 * is nested in, so every statement has to be able to say which list it sits in
 * and at what position. A statement inside a body says so through its parent;
 * a statement at the top of a file has no parent, and is found here instead.
 *
 * @visibility root
 */
final class SourceTree
{
    /**
     * @var WeakMap<Stmt, array{string, int}>
     */
    private WeakMap $roots;

    /**
     * @var array<string, list<Stmt>>
     */
    private array $files = [];

    /**
     * Records the top level of every file.
     *
     * @param list<ParsedFile> $files
     */
    public function __construct(array $files = [])
    {
        $this->roots = new WeakMap();
        foreach ($files as $file) {
            $this->files[$file->path] = $file->statements;
            foreach ($file->statements as $index => $statement) {
                $this->roots[$statement] = [$file->path, $index];
            }
        }
    }

    /**
     * The statement a node is part of, or the function-like whose expression body holds it.
     *
     * An arrow function has no statements, so a call written in one stops at
     * the arrow function itself rather than at the statement that defines it:
     * the arrow function's parameters are its own, not the enclosing body's.
     */
    public function anchorOf(Node $node): Stmt|FunctionLike|null
    {
        $current = $node;
        while ($current instanceof Node) {
            if ($current !== $node && $current instanceof FunctionLike) {
                return $current;
            }
            if ($current instanceof Stmt && $this->isListed($current)) {
                return $current;
            }
            $parent = $current->getAttribute('parent');
            $current = $parent instanceof Node ? $parent : null;
        }

        return null;
    }

    /**
     * Whether a statement sits in a list of statements that run one after another.
     *
     * The arms of a conditional, a switch or a try are statements too, but
     * they sit in the statement that owns them rather than in a list that runs.
     */
    public function isListed(Stmt $statement): bool
    {
        if ($statement instanceof Stmt\ElseIf_ || $statement instanceof Stmt\Else_ || $statement instanceof Stmt\Case_
            || $statement instanceof Stmt\Catch_ || $statement instanceof Stmt\Finally_) {
            return false;
        }
        $parent = $statement->getAttribute('parent');

        return !$parent instanceof Stmt\ClassLike;
    }

    /**
     * The node that owns the list a statement sits in, the list, and the statement's position in it.
     *
     * @return array{Node|null, list<Stmt>, int}|null
     */
    public function locate(Stmt $statement): ?array
    {
        $root = $this->roots[$statement] ?? null;
        if ($root !== null) {
            return [null, $this->files[$root[0]], $root[1]];
        }
        $parent = $statement->getAttribute('parent');
        if (!$parent instanceof Node) {
            return null;
        }
        $list = $this->listOf($parent);
        $index = array_search($statement, $list, true);

        return is_int($index) ? [$parent, $list, $index] : null;
    }

    /**
     * The statements a node runs one after another, or an empty list when it runs none.
     *
     * @return list<Stmt>
     */
    public function listOf(Node $parent): array
    {
        if ($parent instanceof FunctionLike) {
            return array_values($parent->getStmts() ?? []);
        }
        if ($parent instanceof Stmt\If_ || $parent instanceof Stmt\ElseIf_ || $parent instanceof Stmt\Else_
            || $parent instanceof Stmt\While_ || $parent instanceof Stmt\Do_ || $parent instanceof Stmt\For_
            || $parent instanceof Stmt\Foreach_ || $parent instanceof Stmt\Case_ || $parent instanceof Stmt\TryCatch
            || $parent instanceof Stmt\Catch_ || $parent instanceof Stmt\Finally_ || $parent instanceof Stmt\Block
            || $parent instanceof Stmt\Namespace_) {
            return array_values($parent->stmts);
        }
        if ($parent instanceof Stmt\Declare_) {
            return array_values($parent->stmts ?? []);
        }

        return [];
    }

    /**
     * The file a node is written in, or an empty string when it is not in any recorded file.
     */
    public function fileOf(Node $node): string
    {
        $current = $node;
        while ($current instanceof Node) {
            if ($current instanceof Stmt) {
                $root = $this->roots[$current] ?? null;
                if ($root !== null) {
                    return $root[0];
                }
            }
            $parent = $current->getAttribute('parent');
            $current = $parent instanceof Node ? $parent : null;
        }

        return '';
    }

    /**
     * The function-like body a node is written in, or null when it is written at the top of a file.
     */
    public function bodyOf(Node $node): ?FunctionLike
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof FunctionLike) {
                return $parent;
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }
}
