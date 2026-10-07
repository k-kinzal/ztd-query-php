<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Conversion;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;

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
    public function cast(Evaluable $operand, CastTarget $target): Evaluable
    {
        return new Conversion($operand, $this->domain($operand->domain(), $target), $target->length !== null && in_array($target->kind, [CastKind::Char, CastKind::NationalChar, CastKind::Binary], true) ? (int) $target->length : null, $target->kind->value);
    }

    /**
     * Answers the domain of a target type for an operand domain.
     *
     * @throws \MySqlMemory\Error\SqlError When the target is not one the emulator converts to
     */
    public function domain(Domain $operand, CastTarget $target): Domain
    {
        $nullable = $operand->nullable;
        $decimals = $target->length === null ? 0 : (int) $target->length;
        $domain = match ($target->kind) {
            CastKind::Signed => Domain::integer(Field::LongLong, 21),
            CastKind::Unsigned => Domain::integer(Field::LongLong, 21, true),
            CastKind::Decimal => Domain::decimal($target->length === null ? 10 : (int) $target->length, $target->scale === null ? 0 : (int) $target->scale),
            CastKind::Double, CastKind::Real => Domain::double(22),
            CastKind::Float => $target->length !== null && (int) $target->length > 24 ? Domain::double(22) : new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED),
            CastKind::Date => new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::binary(), true),
            CastKind::Time => new Domain(Kind::Time, Field::Time, 10 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, Collation::binary(), true),
            CastKind::DateTime => new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, Collation::binary(), true),
            CastKind::Year => new Domain(Kind::Year, Field::Year, 4, 0, true, Collation::binary(), true),
            CastKind::Char, CastKind::NationalChar => $this->text($operand, $target),
            CastKind::Binary => Domain::string($target->length === null ? $this->length($operand) : (int) $target->length, Collation::binary()),
            CastKind::Json => new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')),
            default => throw ErrorCode::NotSupportedYet->error('CAST AS ' . $target->kind->value),
        };

        return $domain->withNullable($nullable || $domain->nullable);
    }

    /**
     * Answers the domain of CHAR: the connection collation, or the character set named.
     */
    public function text(Domain $operand, CastTarget $target): Domain
    {
        $collation = $this->compiler->settings->connectionCollation;
        if ($target->kind === CastKind::NationalChar) {
            $collation = Collation::known('utf8mb3_general_ci');
        } elseif ($target->charset !== null) {
            $collation = match ($target->charset->form) {
                CharsetForm::Ascii => Collation::known('ascii_general_ci'),
                CharsetForm::Unicode => Collation::known('utf8mb4_0900_ai_ci'),
                CharsetForm::Byte, CharsetForm::Binary => Collation::binary(),
                default => $target->charset->charset === null ? $collation : (Charset::named($target->charset->charset->value)?->defaultCollation($this->compiler->settings->release()) ?? $collation),
            };
        }
        $length = $target->length === null ? $this->length($operand) : (int) $target->length;

        return Domain::string($length, $collation)->withCollation($collation, Coercibility::Implicit);
    }

    /**
     * Answers the length in characters of the text of an operand.
     */
    public function length(Domain $operand): int
    {
        return match ($operand->kind) {
            Kind::Double => 22,
            default => $operand->length,
        };
    }
}
