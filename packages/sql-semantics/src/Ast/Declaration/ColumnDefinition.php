<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Declaration;

use SqlParser\Parser\Node;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Internal declaration syntax awaiting name and type binding.
 *
 * @visibility SqlSemantics
 */
final class ColumnDefinition
{
    /**
     * @param string $name Resolved column name
     * @param TypeDescriptor $type Declared database type
     * @param Nullability $nullability Declaration-level NULL allowance
     * @param Node $source Original column declaration
     * @param Node|null $defaultExpression Original default syntax, evaluated on insertion
     * @param list<Node> $attributes Complete column attributes, including collation and identity
     * @param Node|null $generatedExpression Generated value expression
     * @param array<string, string|bool|list<string>> $options Named column options with decoded values
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node $source,
        public readonly ?Node $defaultExpression = null,
        public readonly array $attributes = [],
        public readonly ?Node $generatedExpression = null,
        public readonly array $options = [],
    ) {
    }
}
