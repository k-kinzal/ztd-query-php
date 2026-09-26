<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\TypeDeclaration;

/**
 * Interprets a declared type into typed facts using the dialect's type policy.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type, including table-dependent storage rules and the column facts it implies.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return $this->dialect->platform()->types()->read($node, $values, $table);
    }
}
