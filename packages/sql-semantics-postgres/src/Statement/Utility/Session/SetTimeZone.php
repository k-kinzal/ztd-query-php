<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SET [LOCAL] TIME ZONE value`: a request to change the time zone of the session.
 *
 * Rule: PG-SET-003. Mirrors PostgreSQL's `VariableSetStmt` on `timezone`
 * written with the TIME ZONE keywords. The value is a zone name as a string
 * or a plain identifier (a keyword must be quoted there), a number of hours east of UTC, an interval constant, or
 * LOCAL or DEFAULT. Facts: the interval constant has the interval type.
 * Diagnostic: an interval restricted to fields other than HOUR or HOUR TO
 * MINUTE is rejected by the server while parsing.
 * Source: https://www.postgresql.org/docs/17/sql-set.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a time zone set by an interval
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SET TIME ZONE INTERVAL '-08:00' HOUR TO MINUTE");
 *     [$operation->statement->zone->value->value, $operation->toString()] // => ['-08:00', "SET TIME ZONE INTERVAL '-08:00' HOUR TO MINUTE"]
 * @example Refusing a constant of another type
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('date')]))),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('1'),
 *     )) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SetTimeZone implements Statement
{
    use Snapshot;

    /**
     * @param StringConstant|Name|SignedNumber|TypedLiteral|ZoneKeyword $zone The zone: a name, an offset in hours, an interval constant, or a keyword
     * @param bool $local Whether the change lasts for the current transaction only
     */
    public function __construct(public readonly StringConstant|Name|SignedNumber|TypedLiteral|ZoneKeyword $zone, public readonly bool $local = false)
    {
        Check::input(!$zone instanceof TypedLiteral || $zone->type->designation instanceof IntervalDesignation, 'The constant of SET TIME ZONE is an interval.');
    }

    /**
     * Derives the interval constant and reports an interval of other fields than hours and minutes.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if (!$this->zone instanceof TypedLiteral) {
            return;
        }
        $derivation->scalar($this->zone, $derivation->environment());
        $designation = $this->zone->type->designation;
        if ($designation instanceof IntervalDesignation && !in_array($designation->fields, [null, IntervalFields::Hour, IntervalFields::HourToMinute], true)) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::ZoneIntervalFields));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET');
        if ($this->local) {
            $out->keyword('LOCAL');
        }
        $out->keyword('TIME', 'ZONE');
        if ($this->zone instanceof ZoneKeyword) {
            $out->keyword($this->zone->value);

            return;
        }
        if ($this->zone instanceof Name) {
            $out->name($this->zone, NameUse::Identifier);

            return;
        }
        $out->node($this->zone);
    }
}
