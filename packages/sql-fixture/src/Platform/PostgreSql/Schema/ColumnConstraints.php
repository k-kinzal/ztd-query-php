<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression as DefaultClause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Statement\Scalar;

/**
 * The column constraints that decide how fixture values are generated.
 *
 * Nullability is not read here: the analysis of the statement decides it.
 *
 * @visibility root
 */
final class ColumnConstraints
{
    /**
     * Records the constraints a column declares, defaulting to a plain column without a default.
     */
    public function __construct(
        public readonly bool $primaryKey = false,
        public readonly bool $identity = false,
        public readonly ?Scalar $default = null,
    ) {
    }

    /**
     * Reads the constraints written after a column's type.
     *
     * @param list<object> $qualifiers
     */
    public function read(array $qualifiers): self
    {
        $primaryKey = false;
        $identity = false;
        $default = null;
        foreach ($qualifiers as $qualifier) {
            $primaryKey = $primaryKey || $qualifier instanceof ColumnPrimaryKey;
            $identity = $identity || $qualifier instanceof Identity;
            if ($qualifier instanceof DefaultClause) {
                $default = $qualifier->value;
            }
        }

        return new self($primaryKey, $identity, $default);
    }
}
