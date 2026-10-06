<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The keyword DEFAULT used as a value: the default of the column it is assigned to.
 *
 * Mirrors the server's `Item_default_value` without an argument. The value
 * means something only where a statement assigns it to a column: a row of
 * INSERT or REPLACE, an assignment of SET, ON DUPLICATE KEY UPDATE, UPDATE
 * or LOAD DATA.
 *
 * Rule: MYSQL-DML-DEFAULT-001. The statement that assigns the value derives
 * it at a position whose environment holds exactly one field, the column it
 * is assigned to; the value has the type and nullability of that column,
 * because the default is stored as a value of the column. Anywhere else,
 * such as a row of a VALUES statement, the server rejects DEFAULT and the
 * fact is that diagnostic. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/values.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the value DEFAULT of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t (a) VALUES (DEFAULT)');
 *     $insert->statement->rows[0]->values[0] instanceof \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest // => true
 */
final class DefaultRequest implements Scalar
{
    use Snapshot;

    /**
     * Derives the type and nullability of the column the value is assigned to.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if (count($environment->aliases) === 1) {
            return new ScalarFact($environment->aliases[0]->type, $environment->aliases[0]->nullability);
        }
        $problem = new WriteMisuse(WriteRule::DefaultOutsideInsert);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT');
    }
}
