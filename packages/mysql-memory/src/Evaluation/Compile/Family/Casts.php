<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Operator\Zoned;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles CAST and CONVERT to a type: the domain of the target and the conversion into it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Casts
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles the conversion of an operand to a target type.
     *
     * @throws \MySqlMemory\Error\SqlError When the target is not one the emulator converts to
     */
    public function cast(Evaluable $operand, CastTarget $target, Scalar $node): Evaluable
    {
        return new Conversion($operand, $this->compiler->domain($node), $target->length !== null && in_array($target->kind, [CastKind::Char, CastKind::NationalChar, CastKind::Binary], true) ? (int) $target->length : null, $target->kind->value);
    }

    /**
     * Compiles CAST(value AT TIME ZONE zone AS DATETIME): a TIMESTAMP value as a DATETIME in UTC.
     *
     * The value is a TIMESTAMP column or NULL, else the cast is an error; the zone is 'UTC' or
     * '+00:00', spelled so, else it is an unknown time zone. A precision above 6 is refused before
     * (Problems).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is no TIMESTAMP, or the zone is not UTC
     */
    public function atTimeZone(AtTimeZone $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $domain = $operand->domain();
        if ($domain->field !== Field::Timestamp && $domain->kind !== Kind::Null) {
            throw ErrorCode::TimeZoneCast->error();
        }
        if ($node->zone->value !== 'UTC' && $node->zone->value !== '+00:00') {
            throw ErrorCode::UnknownTimeZone->error($node->zone->value);
        }

        return new Zoned($operand, $this->compiler->domain($node));
    }
}
