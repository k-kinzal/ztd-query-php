<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression as DefaultClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;

/**
 * The column constraints that decide how fixture values are generated.
 *
 * Nullability and generation are not read here: the analysis of the
 * statement decides them.
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
        public readonly bool $autoIncrement = false,
        public readonly DefaultLiteral|DefaultWord|null $default = null,
    ) {
    }

    /**
     * Reads the constraints written after a column's type; the last DEFAULT clause decides the default.
     *
     * @param list<ColumnConstraint> $constraints
     */
    public function read(array $constraints): self
    {
        $primaryKey = false;
        $autoIncrement = false;
        $default = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof ColumnPrimaryKey) {
                $primaryKey = true;
                $autoIncrement = $autoIncrement || $constraint->autoincrement;
            } elseif ($constraint instanceof DefaultLiteral || $constraint instanceof DefaultWord) {
                $default = $constraint;
            } elseif ($constraint instanceof DefaultClause) {
                $default = null;
            }
        }

        return new self($primaryKey, $autoIncrement, $default);
    }
}
