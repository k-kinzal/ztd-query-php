<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Node;

/**
 * Refuses a fractional seconds precision above 6 in a CAST or CONVERT to TIME or DATETIME and in AT TIME ZONE, as the server does while it reads the statement.
 *
 * The error is ER_TOO_BIG_PRECISION, named for CAST in both cases; the casts are checked in
 * their order, and AT TIME ZONE after them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility MySqlMemory
 */
final class Precision
{
    /**
     * Raises the error of a CAST or CONVERT to TIME or DATETIME, or of AT TIME ZONE, with a precision above 6 (ER_TOO_BIG_PRECISION), in the order of the casts and then of AT TIME ZONE.
     *
     * @throws \MySqlMemory\Error\SqlError When such a precision is found
     */
    public function check(Node $statement): void
    {
        foreach ((new Walker())->find($statement, Cast::class) as $cast) {
            $target = $cast->target;
            if (($target->kind === CastKind::Time || $target->kind === CastKind::DateTime) && $target->length !== null && (int) $target->length > 6) {
                throw SchemaError::TooBigPrecision->error((int) $target->length, 'CAST', 6);
            }
        }
        foreach ((new Walker())->find($statement, AtTimeZone::class) as $zoned) {
            if ($zoned->precision !== null && (int) $zoned->precision > 6) {
                throw SchemaError::TooBigPrecision->error((int) $zoned->precision, 'CAST', 6);
            }
        }
    }
}
