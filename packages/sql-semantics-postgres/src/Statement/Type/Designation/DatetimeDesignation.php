<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\Modifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * The type spelled `TIMESTAMP` or `TIME` with an optional precision and time zone clause.
 *
 * Rule: PG-TYPE-DATETIME-001. WITH TIME ZONE selects `timestamptz` or
 * `timetz`; no clause and WITHOUT TIME ZONE select `timestamp` or `time`. A
 * precision above 6 is reduced to 6 by the server.
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DatetimeDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param DatetimeKeyword $keyword The keyword
     * @param IntegerConstant|null $precision The number of fractional second digits
     * @param ZoneOption|null $zone The time zone clause written
     */
    public function __construct(public readonly DatetimeKeyword $keyword, public readonly ?IntegerConstant $precision = null, public readonly ?ZoneOption $zone = null)
    {
    }

    /**
     * Answers the catalog type the spelling denotes.
     */
    public function builtin(): Builtin
    {
        return match ($this->keyword) {
            DatetimeKeyword::Timestamp => $this->zone === ZoneOption::WithTimeZone ? Builtin::Timestamptz : Builtin::Timestamp,
            DatetimeKeyword::Time => $this->zone === ZoneOption::WithTimeZone ? Builtin::Timetz : Builtin::Time,
        };
    }

    /**
     * Answers the date/time type with its precision.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        return $this->precision === null ? new Known($this->builtin()) : (new Modifiers())->constrained($this->builtin(), [$this->precision->digits]);
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name($this->builtin()->value);
    }

    /**
     * Derives nothing: the precision is a constant.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword, the precision and the time zone clause.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keyword->value);
        if ($this->precision !== null) {
            $out->symbol('(')->node($this->precision)->symbol(')');
        }
        if ($this->zone !== null) {
            $out->keyword(...explode(' ', $this->zone->value));
        }
    }
}
