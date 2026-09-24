<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Every existing object of one class in the selected schemas.
 * @visibility public
 * @example Reading the schemas
 *     $targets = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass::Tables, ['app']);
 *     $targets->schemas // => ['app']
 */
final class SchemaScopedTargets
{
    /**
     * @param non-empty-list<string> $schemas Ordered schema names
     * @throws InvalidStructure
     */
    public function __construct(public readonly SchemaScopedClass $class, public readonly array $schemas)
    {
        Collections::strings(Collections::nonEmpty($schemas));
        if (in_array('', $schemas, true)) {
            throw new InvalidStructure('A schema-scoped privilege target requires nonempty schema names.');
        }
    }
}
