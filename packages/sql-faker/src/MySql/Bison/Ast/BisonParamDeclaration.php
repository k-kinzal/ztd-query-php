<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %parse-param or %lex-param declaration.
 *
 * Example: %parse-param { THD *thd }
 */
final class BisonParamDeclaration implements BisonDeclaration
{
    /**
     * @param 'parse-param'|'lex-param' $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $code,
    ) {
    }
}
