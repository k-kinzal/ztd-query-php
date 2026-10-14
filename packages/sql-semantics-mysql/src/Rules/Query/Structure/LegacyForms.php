<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

/**
 * Identifies query forms removed before MySQL 8.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacyForms
{
    /**
     * Identifies forms that require a grammar from before MySQL 8.0.
     */
    public function requires(object $value): bool
    {
        return in_array($value::class, [ProcedureAnalyse::class, LateOrdering::class, LeadingUnion::class, OrderedSetOperation::class, WeightLevel::class, WeightLevelRange::class, EscapedRelation::class, ParseGeneratedColumn::class, PartitionEntry::class, AlterTablespaceAccess::class, ResetQueryCache::class, \SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning::class], true)
            || in_array($value, [\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind::Redo, SelectOption::Cache, KeywordFunction::Password, KeywordFunction::OldPassword, PasswordFunction::Password, PasswordFunction::OldPassword, AliasMark::Equals], true)
            || ($value instanceof NestedRelation && $value->relation instanceof DerivedTable)
            || (($value instanceof ColumnUse || $value instanceof TableReference) && $value->dot === OptionalWords::Written)
            || $value instanceof ExplainModifier
            || $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment
            || $value === \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::PasswordHash
            || ($value instanceof DatabaseCollation && $value->collation->name === null)
            || ($value instanceof ResetReplica && $value->terminology === Terminology::Legacy)
            || $this->definition($value)
            || ($value instanceof \SqlSemantics\Statement\Query && $this->query($value));
    }
    /**
     * Identifies ordering and set operands that only legacy query grammars admit.
     */
    public function query(\SqlSemantics\Statement\Query $value): bool
    {
        return ($value instanceof QueryExpression && $value->body instanceof Select && $value->orderBy !== [])
            || ($value instanceof SetOperation && ($value->left instanceof Select && $value->left->trailed() || $value->left instanceof QueryExpression));
    }

    /**
     * Identifies removed options of account, table and routine declarations.
     */
    public function definition(object $value): bool
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping => array_filter($value->items, static fn (\SqlSemantics\Platform\MySql\Statement\Query\OrderItem $item): bool => $item->direction !== null) !== [],
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy => $this->grant($value),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset => $value->charset->name === null,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable => $value->ignore,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable => $value->temporaryWords > 1,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Parameter => $value::nameProblem($value->name) !== null || ($value->collation !== null && $value->collation->name === null),
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration => $value->collation !== null && $value->collation->name === null,
            default => false,
        };
    }
    /**
     * Identifies authentication and WITH options only legacy GRANT syntax admits.
     */
    public function grant(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges|\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy $value): bool
    {
        if (array_filter($value->grantees, static fn (\SqlSemantics\Platform\MySql\Statement\Account\User\Grantee $user): bool => $user->identification !== null) !== []) {
            return true;
        }
        return $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges
            && ($value->tls !== null || count($value->options) > 1 || array_filter($value->options, static fn (\SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption $option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit) !== []);
    }

}
