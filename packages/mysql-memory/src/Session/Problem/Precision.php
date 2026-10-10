<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Statement\Node;

/**
 * Refuses an excessive CAST precision or an AT TIME ZONE fraction while the statement is read.
 *
 * SQL Semantics supplies each cast's precision problem: at most 53 bits for FLOAT and 6 digits
 * for temporal fractions. The error is ER_TOO_BIG_PRECISION, named for CAST in each case;
 * the casts are checked in their order, and AT TIME ZONE after them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility MySqlMemory
 */
final class Precision
{
    /**
     * Raises cast precision problems, followed by AT TIME ZONE precision problems.
     *
     * @throws \MySqlMemory\Error\SqlError When such a precision is found
     */
    public function check(Node $statement): void
    {
        foreach ((new Walker())->find($statement, Cast::class) as $cast) {
            $problem = $cast->precisionProblem();
            if ($problem !== null) {
                throw SchemaError::TooBigPrecision->error($problem->precision, $problem->function, $problem->maximum);
            }
        }
        foreach ((new Walker())->find($statement, AtTimeZone::class) as $zoned) {
            if ($zoned->precision !== null && (int) $zoned->precision > 6) {
                throw SchemaError::TooBigPrecision->error((int) $zoned->precision, 'CAST', 6);
            }
        }
    }

    /**
     * Answers an error as the release reports it: MySQL 5.6 writes ER_TOO_BIG_PRECISION as "Too big precision ... for column ..." (verified on a live 5.6.51 server); any other error, or release, is answered as it is.
     */
    public function legacy(\MySqlMemory\Error\SqlError $error, \SqlSemantics\Contract\GrammarRelease $release): \MySqlMemory\Error\SqlError
    {
        if ($release !== \SqlSemantics\Contract\GrammarRelease::MySql5651 || $error->error !== SchemaError::TooBigPrecision || preg_match("/\\AToo-big precision (\\d+) specified for '(.*)'\\. Maximum is (\\d+)\\.\\z/s", $error->getMessage(), $match) !== 1) {
            return $error;
        }

        return new \MySqlMemory\Error\SqlError($error->error, "Too big precision {$match[1]} specified for column '{$match[2]}'. Maximum is {$match[3]}.", $error->getPrevious(), $error->following, $error->signalled, null, $error->recorded);
    }
}
