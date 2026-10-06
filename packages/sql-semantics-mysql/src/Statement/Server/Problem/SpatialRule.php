<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

/**
 * The rules of spatial reference system statements whose violation the server reports while it parses them.
 *
 * Each case holds the server error it mirrors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 *
 * @visibility public
 * @example Reading the server error of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::MissingAttribute->value // => 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE'
 */
enum SpatialRule: string
{
    case IdentifierOutOfRange = 'ER_DATA_OUT_OF_RANGE';
    case IdentifierZero = 'ER_CANT_MODIFY_SRID_0';
    case RepeatedAttribute = 'ER_SRS_MULTIPLE_ATTRIBUTE_DEFINITIONS';
    case MissingAttribute = 'ER_SRS_MISSING_MANDATORY_ATTRIBUTE';
    case BlankName = 'ER_SRS_NAME_CANT_BE_EMPTY_OR_WHITESPACE';
    case BlankOrganization = 'ER_SRS_ORGANIZATION_CANT_BE_EMPTY_OR_WHITESPACE';
    case ControlCharacter = 'ER_SRS_INVALID_CHARACTER_IN_ATTRIBUTE';
    case TooLong = 'ER_SRS_ATTRIBUTE_STRING_TOO_LONG';
}
