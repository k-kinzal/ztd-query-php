<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use RuntimeException;

/**
 * Retains a failed, non-NULL geometry result through enclosing expressions.
 *
 * A failed geohash point construction leaves a non-NULL invalid geometry payload.
 * Geometry readers record an additional GIS condition; coordinate readers in
 * modern releases only propagate the original condition. No input syntax is
 * needed to distinguish this from a constructor that failed with a NULL result.
 * Verified by composing point constructors and spatial readers on native SQL.
 *
 * @visibility MySqlMemory
 */
final class FailedGeometry extends RuntimeException
{
    /**
     * Keeps the original SQL condition together with its non-NULL geometry result.
     */
    public static function condition(SqlError $error): SqlError
    {
        return new SqlError($error->error, $error->getMessage(), new self(previous: $error), $error->following, $error->signalled, $error->getCode(), $error->recorded);
    }

    /**
     * Consumes an operand, recording the reader's additional condition when required.
     *
     * @throws SqlError When operand evaluation fails, retaining the reader's diagnostics
     */
    public static function read(Evaluable $operand, Frame $frame, ?string $function): int|float|string|null
    {
        try {
            return $operand->evaluate($frame);
        } catch (SqlError $error) {
            if (!$error->getPrevious() instanceof self || $function === null) {
                throw $error;
            }
            $following = $error->following;
            $following[] = [DataError::GisInvalidData->number(), DataError::GisInvalidData->message($function)];
            throw new SqlError($error->error, $error->getMessage(), $error, $following, $error->signalled, $error->getCode(), $error->recorded);
        }
    }
}
