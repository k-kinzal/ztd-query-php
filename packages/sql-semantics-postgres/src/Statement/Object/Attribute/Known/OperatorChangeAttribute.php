<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `ALTER OPERATOR ... SET` recognizes.
 *
 * `AlterOperator` reads `restrict` and `join` as selectivity estimators
 * (without a value, or with NONE, the estimator is removed), and from
 * PostgreSQL 17 `commutator` and `negator` as operators and `merges` and
 * `hashes` as Booleans. `leftarg`, `rightarg`, `function` and `procedure`,
 * and in PostgreSQL 16 also `commutator`, `negator`, `merges` and `hashes`,
 * are recognized only to be refused: they cannot be changed. Any other
 * attribute is an error.
 * Source: https://www.postgresql.org/docs/17/sql-alteroperator.html, `AlterOperator` in `src/backend/commands/operatorcmds.c` of PostgreSQL 16 and 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorChangeAttribute::from('negator')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Operator
 */
enum OperatorChangeAttribute: string implements KnownAttribute
{
    case Restrict = 'restrict';
    case Join = 'join';
    case Commutator = 'commutator';
    case Negator = 'negator';
    case Merges = 'merges';
    case Hashes = 'hashes';
    case Leftarg = 'leftarg';
    case Rightarg = 'rightarg';
    case Function = 'function';
    case Procedure = 'procedure';

    /**
     * How the command reads each attribute; the attributes that cannot be changed are not read.
     */
    private const READINGS = [
        'restrict' => Reading::Function,
        'join' => Reading::Function,
        'commutator' => Reading::Operator,
        'negator' => Reading::Operator,
        'merges' => Reading::Boolean,
        'hashes' => Reading::Boolean,
        'leftarg' => Reading::Ignored,
        'rightarg' => Reading::Ignored,
        'function' => Reading::Ignored,
        'procedure' => Reading::Ignored,
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
