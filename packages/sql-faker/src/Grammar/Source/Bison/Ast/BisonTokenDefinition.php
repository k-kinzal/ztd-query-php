<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Ast;

/**
 * One terminal declared by a `%token` directive.
 *
 * Example: `TOKEN1 123 "alias"`. The code and the alias are optional and
 * independent of each other, so both are absent unless the grammar wrote them.
 */
final class BisonTokenDefinition
{
    /**
     * @param string $name Terminal being declared
     * @param int|null $number Explicit token code, or null when Bison assigns one
     * @param string|null $alias Quoted spelling the terminal may also be written as
     */
    public function __construct(
        public readonly string $name,
        public readonly ?int $number,
        public readonly ?string $alias,
    ) {
    }
}
