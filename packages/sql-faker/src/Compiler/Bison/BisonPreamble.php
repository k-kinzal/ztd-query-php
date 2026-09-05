<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison;

use SqlFaker\Compiler\Bison\Ast\BisonDeclaration;

/**
 * Everything a grammar file states before its rules.
 *
 * The prologue and the declarations are read in one pass because they are
 * interleaved, but they are two different things to the rest of the parser, so
 * they travel together rather than as an unlabelled pair.
 *
 * @visibility root
 */
final class BisonPreamble
{
    /**
     * @param string|null $prologue Host-language code from `%{ ... %}`, or null when the file has none
     * @param list<BisonDeclaration> $declarations Declarations in the order they were written
     */
    public function __construct(
        public readonly ?string $prologue,
        public readonly array $declarations,
    ) {
    }
}
