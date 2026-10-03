<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Language;
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
     * @param Language $language The grammar release and lexical settings of the declaration
     * @param Node|null $table Enclosing table declaration, for options that change how a type is stored
     * @throws SemanticException When the declaration is outside the modeled surface or invalid
     */
    public function read(Node $node, Language $language, ?Node $table = null): TypeDeclaration;

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool;
}
