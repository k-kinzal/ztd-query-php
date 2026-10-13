<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastAtLocal;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;

/**
 * Identifies language features introduced in MySQL 8.0 or later.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ModernForms
{
    /**
     * Answers whether the model needs a modern grammar generation.
     */
    public function requires(object $value): bool
    {
        return $value instanceof WithClause || $value instanceof WindowSpecification
            || in_array($value::class, [CommonTableExpression::class, ValuesQuery::class, ExplicitTable::class, JsonTable::class, JsonValueCall::class, AtTimeZone::class, CastAtLocal::class, OdbcJoin::class, TableSample::class, DatabaseReadOnly::class, DatabaseEncryption::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs::class], true)
            || match (true) {
                $value instanceof Select => $value->qualify !== null || count($value->locking) > 1,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement => count($value->locking) > 1,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause => $value->strength === \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Share || $value->tables !== [] || $value->action !== null,
                $value instanceof ResetBinaryLogs, $value instanceof ResetReplica => $value->terminology === Terminology::Current,
                $value instanceof DerivedTable => $value->lateral || $value->columns !== [],
                default => in_array($value, [SetOperator::Intersect, SetOperator::Except], true),
            };
    }
}
