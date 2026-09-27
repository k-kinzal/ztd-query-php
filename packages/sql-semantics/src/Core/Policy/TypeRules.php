<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

/**
 * Supplies declared type interpretation.
 *
 * @visibility SqlSemantics
 */
interface TypeRules
{
    /**
     * Reads a declared type into typed facts, including table-dependent storage rules and implied column facts.
     *
     * @param ValueReader $values Lowers declaration parts that the type keeps as typed values
     * @param Node|null $table Enclosing table declaration, for options that change how a type is stored
     * @throws SemanticException When the declaration is outside the modeled surface or invalid
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration;

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool;
}
