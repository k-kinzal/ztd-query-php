<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Configuration parameters selected by their possibly dotted names.
 * @visibility public
 * @example Reading a customized parameter name
 *     $targets = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets([new \SqlSemantics\Model\Relation\QualifiedName(['app', 'limit'])]);
 *     $targets->parameters[0]->parts // => ['app', 'limit']
 */
final class ParameterTargets
{
    /**
     * @param non-empty-list<QualifiedName> $parameters Ordered parameter names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $parameters)
    {
        Collections::objects(Collections::nonEmpty($parameters), QualifiedName::class);
    }
}
