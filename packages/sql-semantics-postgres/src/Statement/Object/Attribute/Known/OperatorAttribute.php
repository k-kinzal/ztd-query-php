<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE OPERATOR` recognizes.
 *
 * `DefineOperator` reads `leftarg` and `rightarg` as type names,
 * `function` and its older spelling `procedure` as the operator's function,
 * `restrict` and `join` as its selectivity estimators, `commutator` and
 * `negator` as operators and `hashes` and `merges` as Booleans. The obsolete
 * `sort1`, `sort2`, `ltcmp` and `gtcmp` mean `merges` whatever their value. Any
 * other attribute draws a warning and is ignored.
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, `DefineOperator` in `src/backend/commands/operatorcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute::from('procedure')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function
 */
enum OperatorAttribute: string implements KnownAttribute
{
    case Leftarg = 'leftarg';
    case Rightarg = 'rightarg';
    case Function = 'function';
    case Procedure = 'procedure';
    case Commutator = 'commutator';
    case Negator = 'negator';
    case Restrict = 'restrict';
    case Join = 'join';
    case Hashes = 'hashes';
    case Merges = 'merges';
    case Sort1 = 'sort1';
    case Sort2 = 'sort2';
    case Ltcmp = 'ltcmp';
    case Gtcmp = 'gtcmp';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'leftarg' => Reading::Type,
        'rightarg' => Reading::Type,
        'function' => Reading::Function,
        'procedure' => Reading::Function,
        'commutator' => Reading::Operator,
        'negator' => Reading::Operator,
        'restrict' => Reading::Function,
        'join' => Reading::Function,
        'hashes' => Reading::Boolean,
        'merges' => Reading::Boolean,
        'sort1' => Reading::Ignored,
        'sort2' => Reading::Ignored,
        'ltcmp' => Reading::Ignored,
        'gtcmp' => Reading::Ignored,
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
