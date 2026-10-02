<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The integer types of MySQL.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/integer-types.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::BigInt->value // => 'BIGINT'
 */
enum IntegralKind: string
{
    case TinyInt = 'TINYINT';
    case SmallInt = 'SMALLINT';
    case MediumInt = 'MEDIUMINT';
    case Int = 'INT';
    case BigInt = 'BIGINT';
}
