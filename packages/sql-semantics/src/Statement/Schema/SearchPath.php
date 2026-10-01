<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Identifier\Name;

/**
 * Ordered namespaces used to resolve an unqualified relation name.
 * @visibility public
 * @example Selecting the default declaration namespace
 *     $path = new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('public'));
 *     $path->schemas[0]->value // => 'public'
 */
final class SearchPath
{
    /**
     * @var non-empty-list<Name>
     */
    public readonly array $schemas;

    /**
     * Requires at least one schema and preserves lookup precedence.
     */
    public function __construct(Name $first, Name ...$rest)
    {
        $this->schemas = [$first, ...array_values($rest)];
    }
}
