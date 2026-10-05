<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE AGGREGATE` recognizes.
 *
 * `DefineAggregate` reads the support functions (`sfunc`, `finalfunc`,
 * `combinefunc`, `serialfunc`, `deserialfunc`, `msfunc`, `minvfunc`,
 * `mfinalfunc`) as routine names, `sortop` as an operator, `basetype`,
 * `stype` and `mstype` as type names, `sspace` and `msspace` as integers,
 * the initial conditions as text, `parallel` and the modify attributes as
 * fixed words and the rest as Booleans. `sfunc1`, `stype1` and `initcond1`
 * are obsolete spellings of `sfunc`, `stype` and `initcond`. Any other
 * attribute draws a warning and is ignored.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `DefineAggregate` in `src/backend/commands/aggregatecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute::from('sfunc')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function
 */
enum AggregateAttribute: string implements KnownAttribute
{
    case Sfunc = 'sfunc';
    case Sfunc1 = 'sfunc1';
    case Finalfunc = 'finalfunc';
    case Combinefunc = 'combinefunc';
    case Serialfunc = 'serialfunc';
    case Deserialfunc = 'deserialfunc';
    case Msfunc = 'msfunc';
    case Minvfunc = 'minvfunc';
    case Mfinalfunc = 'mfinalfunc';
    case FinalfuncExtra = 'finalfunc_extra';
    case MfinalfuncExtra = 'mfinalfunc_extra';
    case FinalfuncModify = 'finalfunc_modify';
    case MfinalfuncModify = 'mfinalfunc_modify';
    case Sortop = 'sortop';
    case Basetype = 'basetype';
    case Hypothetical = 'hypothetical';
    case Stype = 'stype';
    case Stype1 = 'stype1';
    case Sspace = 'sspace';
    case Mstype = 'mstype';
    case Msspace = 'msspace';
    case Initcond = 'initcond';
    case Initcond1 = 'initcond1';
    case Minitcond = 'minitcond';
    case Parallel = 'parallel';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'sfunc' => Reading::Function,
        'sfunc1' => Reading::Function,
        'finalfunc' => Reading::Function,
        'combinefunc' => Reading::Function,
        'serialfunc' => Reading::Function,
        'deserialfunc' => Reading::Function,
        'msfunc' => Reading::Function,
        'minvfunc' => Reading::Function,
        'mfinalfunc' => Reading::Function,
        'finalfunc_extra' => Reading::Boolean,
        'mfinalfunc_extra' => Reading::Boolean,
        'finalfunc_modify' => Reading::FinalModification,
        'mfinalfunc_modify' => Reading::FinalModification,
        'sortop' => Reading::Operator,
        'basetype' => Reading::Type,
        'hypothetical' => Reading::Boolean,
        'stype' => Reading::Type,
        'stype1' => Reading::Type,
        'sspace' => Reading::Integer,
        'mstype' => Reading::Type,
        'msspace' => Reading::Integer,
        'initcond' => Reading::Text,
        'initcond1' => Reading::Text,
        'minitcond' => Reading::Text,
        'parallel' => Reading::Parallelism,
    ];

    /**
     * Answers the member with exactly this name, or null.
     */
    public static function named(string $name): ?self
    {
        return self::tryFrom($name);
    }

    /**
     * Answers the attribute name the command compares with.
     */
    public function text(): string
    {
        return $this->value;
    }

    /**
     * Answers how the command reads the attribute's value.
     */
    public function reading(): Reading
    {
        return self::READINGS[$this->value];
    }
}
