<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
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
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
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
            || $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction
            || in_array($value, [\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Row, \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::System, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::RandomPassword, \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect, \SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::Rollup, \SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::Cube], true)
            || in_array($value::class, [\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree::class, \SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility::class, \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement::class, \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption::class, \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf::class, \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege::class, \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody::class, CommonTableExpression::class, ValuesQuery::class, ExplicitTable::class, JsonTable::class, JsonValueCall::class, AtTimeZone::class, OdbcJoin::class, TableSample::class, DatabaseReadOnly::class, DatabaseEncryption::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs::class], true)
            || match (true) {
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption => strtoupper($value->algorithm->value ?? '') === 'INSTANT',
                $value instanceof Select => $this->query($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain, $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection => $this->explain($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint => !$value->name()->available(\SqlSemantics\Contract\GrammarRelease::MySql5744),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification => $this->authentication($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot => (new StatementQuery())->requiresModern($value->query),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement => count($value->locking) > 1,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause => $this->locking($value),
                $value instanceof ResetBinaryLogs => $value->terminology === Terminology::Current || $value->first !== null,
                $value instanceof ResetReplica, $value instanceof SourceOption,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica => $value->terminology === Terminology::Current,
                $value instanceof DerivedTable => $value->lateral || $value->columns !== [],
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure => $value->ifNotExists,
                default => in_array($value, [SetOperator::Intersect, SetOperator::Except], true),
            };
    }
    /**
     * Identifies EXPLAIN features that cannot accompany removed query or DML forms.
     */
    public function explain(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain|\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection $value): bool
    {
        return $value->analyze || $value->into !== null || ($value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain && $value->database !== null);
    }
    /**
     * Identifies clauses that require modern grammar in every query position.
     */
    public function query(Select $value): bool
    {
        return $value->qualify !== null || count($value->locking) > 1;
    }

    /**
     * Identifies additional factors and password management introduced in modern releases.
     */
    public function authentication(\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification $value): bool
    {
        return $value->factors !== [] || $value->initial !== null || $value->replace !== null || $value->retainCurrent || $value->discardOld;
    }
    /**
     * Identifies the expanded locking clauses introduced in MySQL 8.0.
     */
    public function locking(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause $value): bool
    {
        return $value->strength === \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Share || $value->tables !== [] || $value->action !== null;
    }

}
