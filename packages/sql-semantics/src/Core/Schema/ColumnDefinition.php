<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * A column declaration, before a query can change its nullability.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Schema\ColumnDefinition $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class ColumnDefinition
{
    /**
     * @param string $name Resolved column name
     * @param TypeDescriptor $type Declared database type
     * @param Nullability $nullability Declaration-level NULL allowance
     * @param Node $source Original column declaration
     * @param Node|null $defaultExpression Original default syntax, evaluated on insertion
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node $source,
        public readonly ?Node $defaultExpression = null,
    ) {
    }
}
