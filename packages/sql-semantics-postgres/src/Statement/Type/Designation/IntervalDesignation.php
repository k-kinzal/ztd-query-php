<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type spelled `INTERVAL`, restricted to fields, to a precision, or to neither.
 *
 * Rule: PG-TYPE-INTERVAL-001. `INTERVAL fields` restricts the fields and
 * may give a precision after SECOND; `INTERVAL(p)` gives a precision alone.
 * A precision above 6 is reduced to 6 by the server. In an interval constant
 * the fields are written after the string.
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html#DATATYPE-INTERVAL-INPUT. Status: Implemented.
 *
 * @visibility public
 * @example Reading the fields of an interval constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT interval '1 2' day to second(3)");
 *     [$query->toString(), $query->field(0)->type->descriptor->name()] // => ["SELECT INTERVAL '1 2' DAY TO SECOND (3)", 'interval day to second(3)']
 * @example Rejecting a precision next to fields without seconds
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields::Year, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('3')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class IntervalDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param IntervalFields|null $fields The fields the interval is restricted to
     * @param IntegerConstant|null $precision The number of fractional second digits
     */
    public function __construct(public readonly ?IntervalFields $fields = null, public readonly ?IntegerConstant $precision = null)
    {
        Check::input($precision === null || $fields === null || $fields->seconds(), 'Only the seconds field of an interval takes a precision.');
    }

    /**
     * Answers the interval type with its restriction.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        if ($this->fields === null && $this->precision === null) {
            return new Known(Builtin::Interval);
        }
        $precision = $this->precision === null ? null : (strlen($this->precision->digits) > 1 ? 6 : min(6, (int) $this->precision->digits));

        return new Known(new IntervalSpan($this->fields, $precision));
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name(Builtin::Interval->value);
    }

    /**
     * Derives nothing: the precision is a constant.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the type as a type name.
     */
    public function render(Output $out): void
    {
        $out->keyword('INTERVAL');
        $this->restriction($out);
    }

    /**
     * Writes the type around the string of an interval constant: the fields follow the string.
     */
    public function constant(Output $out, StringConstant $value): void
    {
        $out->keyword('INTERVAL');
        if ($this->fields === null) {
            $this->restriction($out);
            $out->node($value);

            return;
        }
        $out->node($value);
        $this->restriction($out);
    }

    /**
     * Writes the fields and the precision.
     */
    public function restriction(Output $out): void
    {
        if ($this->fields !== null) {
            $out->keyword(...explode(' ', $this->fields->value));
        }
        if ($this->precision !== null) {
            $out->symbol('(')->node($this->precision)->symbol(')');
        }
    }
}
