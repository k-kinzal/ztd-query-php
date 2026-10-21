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
use SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;

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
        return $value instanceof WithClause || $value instanceof WindowSpecification || $this->enumeration($value) || $this->administration($value)
            || in_array($value::class, [\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance::class, \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility::class, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles::class, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole::class, \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction::class, \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree::class, \SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility::class, \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement::class, \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption::class, \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf::class, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMemberOf::class, \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart::class, \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart::class, \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege::class, \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody::class, CommonTableExpression::class, ValuesQuery::class, \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery::class, ExplicitTable::class, JsonTable::class, JsonValueCall::class, AtTimeZone::class, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone::class, OdbcJoin::class, TableSample::class, DatabaseReadOnly::class, DatabaseEncryption::class, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs::class], true)
            || $this->definition($value)
            || match (true) {
                $value instanceof Select => $this->query($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection => $value->qualify !== null,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Delete => $value->table->alias !== null,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain, $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain, $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection => $this->explain($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint => !$value->name()->available(\SqlSemantics\Contract\GrammarRelease::MySql5744),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification => $this->authentication($value),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot => (new StatementQuery())->requiresModern($value->query),
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement => count($value->locking) > 1,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause => $this->locking($value),
                $value instanceof ResetBinaryLogs => $value->terminology === Terminology::Current || $value->first !== null,
                $value instanceof ResetReplica, $value instanceof SourceOption,
                $value instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica => $value->terminology === Terminology::Current,
                default => in_array($value, [SetOperator::Intersect, SetOperator::Except], true),
            };
    }
    /**
     * Identifies administrative statements and operands added after legacy releases.
     */
    public function administration(object $value): bool
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize => $value->number?->hexadecimal === true,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll => $value->ifExists || $value->ignoreUnknownUser,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent => true,
            default => false,
        };
    }

    /**
     * Distinguishes wrapped zero counts interpreted as defaults by modern releases.
     */
    public function partition(\SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause|\SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning $value): bool
    {
        $count = $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause ? $value->partitions : $value->count;
        return $count !== null && (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->option32($count) === 0;
    }

    /**
     * Identifies modern table and routine declaration features.
     */
    public function definition(object $value): bool
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning => $this->partition($value),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges => $this->revocation($value),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint => $value->enforced !== null,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDefaultSetting => true,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions => true,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput => $value->source !== \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile || $value->count !== null || $value->primaryKeyOrder,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable => $value->compression !== null,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel => $value->request->columnLevel(),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName => (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->problem($value->user, $value->host, \SqlSemantics\Contract\GrammarRelease::MySql5744) !== null,
            $value === ElementaryKind::Vector, $value === TextOptionKind::EngineAttribute,
            $value === TextOptionKind::SecondaryEngineAttribute, $value instanceof AutoextendSizeOption => true,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption => strtoupper($value->algorithm->value ?? '') === 'INSTANT',
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateUser => $value->defaultRoles !== [],
            $value instanceof DerivedTable => $value->lateral || $value->columns !== [],
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction, $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure => $value->ifNotExists,
            default => false,
        };
    }

    /**
     * Identifies modern account flags and the ordinary global routine interpretation.
     */
    public function revocation(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges $value): bool
    {
        return $value->ifExists || $value->ignoreUnknownUser || ($value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges && (new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RevokeScopes())->globalAll($value->privileges, $value->kind, $value->level));
    }

    /**
     * Identifies EXPLAIN features that cannot accompany removed query or DML forms.
     */
    public function explain(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain|\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain $value): bool
    {
        return strtoupper($value->format->value ?? '') === 'TREE' || (!$value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection && ($value->analyze || $value->into !== null || $value->database !== null || $value->emptyInto));
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

    /**
     * Identifies modern enum operands independently of their enclosing statement.
     */
    public function enumeration(object $value): bool
    {
        return $value instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind
            || in_array($value, [\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::PersistOnly, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::CreateRole, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::DropRole, \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Row, \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::System, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::RandomPassword, \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect, \SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::Rollup, \SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::Cube], true)
            || ($value instanceof \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind && !in_array($value, [\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Binary, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::NationalChar, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Unsigned, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Date, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Time, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::DateTime, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Decimal, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Json], true));
    }

}
