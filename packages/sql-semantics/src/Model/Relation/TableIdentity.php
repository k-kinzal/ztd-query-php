<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

/**
 * The namespace and name of a relation, independent of its declaration graph.
 *
 * @visibility public
 */
final class TableIdentity
{
    /**

     */
    public function __construct(public readonly string $schema, public readonly string $name)
    {
    }
}
