<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

/**
 * The spatial reference operation owning a declaration constraint.
 *
 * @visibility public
 * @example Naming the replacement operation
 *     \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand::Replace->value // => 'CREATE OR REPLACE'
 */
enum SpatialCommand: string
{
    case Create = 'CREATE';
    case Replace = 'CREATE OR REPLACE';
    case Drop = 'DROP';
}
