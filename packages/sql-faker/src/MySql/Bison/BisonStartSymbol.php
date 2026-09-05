<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison;

use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;

/**
 * Decides which rule a derivation begins from.
 *
 * Bison takes the rule named by `%start`, and the first rule in the file when
 * nothing named one. The fallback is not a guess: it is what Bison itself does,
 * so a grammar without `%start` still has one answer rather than none.
 *
 * @visibility root
 */
final class BisonStartSymbol
{
    /**
     * Reports the start rule of a grammar that has been read.
     *
     * @param list<BisonDeclaration> $declarations Declarations from the grammar's preamble
     * @param non-empty-list<BisonRuleNode> $rules Rules in the order they were declared
     *
     * @return string Name of the rule to derive from
     */
    public function from(array $declarations, array $rules): string
    {
        foreach ($declarations as $declaration) {
            if ($declaration instanceof BisonStartDeclaration) {
                return $declaration->symbol;
            }
        }

        return $rules[0]->name;
    }
}
