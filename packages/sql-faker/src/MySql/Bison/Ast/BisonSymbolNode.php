<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * A symbol on the right-hand side of one rule alternative.
 *
 * The form records how the symbol was written: a name refers to a rule or a
 * declared terminal, while a quoted character is a terminal that stands for
 * itself. The parser needs the difference because only the first can be a rule.
 */
final class BisonSymbolNode
{
    /**
     * @param BisonSymbolType $type How the symbol was spelled in the grammar
     * @param string $value The symbol's name, or the character it stands for
     */
    public function __construct(
        public readonly BisonSymbolType $type,
        public readonly string $value,
    ) {
    }
}
