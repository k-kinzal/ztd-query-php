<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

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
     *
     * @throws LogicException When the dialect policy produces a built-in type it does not support
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        $types = $this->dialect->platform()->types();
        $declaration = $types->read($node, $values, $table);
        $name = $declaration->type->name;
        if ($name instanceof Builtin && !$types->supports($name)) {
            throw new LogicException('The type policy produced an unsupported built-in type: ' . $name->value);
        }

        return $declaration;
    }
}
