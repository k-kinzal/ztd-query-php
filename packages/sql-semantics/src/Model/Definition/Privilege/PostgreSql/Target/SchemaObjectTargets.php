<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Sequences, domains, or types selected by qualified name.
 * @visibility public
 * @example Reading the selection
 *     $targets = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass::Type, [new \SqlSemantics\Model\Relation\QualifiedName(['app', 'money'])]);
 *     $targets->names[0]->parts // => ['app', 'money']
 */
final class SchemaObjectTargets
{
    /**
     * @param non-empty-list<QualifiedName> $names Ordered object names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly SchemaObjectClass $class, public readonly array $names)
    {
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
    }
}
