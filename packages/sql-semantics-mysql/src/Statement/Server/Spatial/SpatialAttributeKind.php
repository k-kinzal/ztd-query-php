<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Spatial;

/**
 * An attribute of CREATE SPATIAL REFERENCE SYSTEM.
 *
 * Mirrors the members of Sql_cmd_srs_attributes. NAME and DEFINITION are
 * mandatory; each attribute is written at most once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization->value // => 'ORGANIZATION'
 */
enum SpatialAttributeKind: string
{
    case Name = 'NAME';
    case Definition = 'DEFINITION';
    case Organization = 'ORGANIZATION';
    case Description = 'DESCRIPTION';
}
