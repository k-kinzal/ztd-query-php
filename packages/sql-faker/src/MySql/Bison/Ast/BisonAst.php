<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Root AST node representing a complete Bison grammar file.
 *
 * @phpstan-type DeclarationList list<BisonDeclaration>
 * @phpstan-type RuleList list<BisonRuleNode>
 */
final class BisonAst
{
    /**
     * @param string $startSymbol The grammar's start symbol (from %start or first rule per Bison spec)
     * @param list<BisonDeclaration> $declarations
     * @param list<BisonRuleNode> $rules
     */
    public function __construct(
        public readonly string $startSymbol,
        public readonly ?string $prologue,
        public readonly array $declarations,
        public readonly array $rules,
        public readonly ?string $epilogue,
    ) {
    }
}
