<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Databases, wrappers, servers, languages, schemas, or tablespaces selected by name.
 * @visibility public
 * @example Reading the selection
 *     $targets = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass::Schema, ['app']);
 *     $targets->names // => ['app']
 */
final class ServerObjectTargets
{
    /**
     * @param non-empty-list<string> $names Ordered object names
     * @throws InvalidStructure
     */
    public function __construct(public readonly ServerObjectClass $class, public readonly array $names)
    {
        Collections::strings(Collections::nonEmpty($names));
        if (in_array('', $names, true)) {
            throw new InvalidStructure('A privilege target requires nonempty object names.');
        }
    }
}
