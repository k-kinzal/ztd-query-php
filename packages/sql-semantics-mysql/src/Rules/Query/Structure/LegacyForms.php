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
        return in_array($value::class, [ProcedureAnalyse::class, LateOrdering::class, LeadingUnion::class, OrderedSetOperation::class, WeightLevel::class, WeightLevelRange::class, EscapedRelation::class, ParseGeneratedColumn::class, PartitionEntry::class, AlterTablespaceAccess::class, ResetQueryCache::class], true)
            || in_array($value, [SelectOption::Cache, KeywordFunction::Password, KeywordFunction::OldPassword, PasswordFunction::Password, PasswordFunction::OldPassword, AliasMark::Equals], true)
            || ($value instanceof NestedRelation && $value->relation instanceof DerivedTable)
            || (($value instanceof ColumnUse || $value instanceof TableReference) && $value->dot === OptionalWords::Written)
            || $value instanceof ExplainModifier
            || ($value instanceof DatabaseCollation && $value->collation->name === null)
            || ($value instanceof ResetReplica && $value->terminology === Terminology::Legacy)
            || $this->definition($value)
            || ($value instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges && ($value->tls !== null || array_filter($value->grantees, static fn (\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification $user): bool => $user->identification !== null) !== [] || array_filter($value->options, static fn (\SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption $option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit) !== []))
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
     * Identifies removed options of table and routine declarations.
     */
    public function definition(object $value): bool
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable => $value->temporaryWords > 1,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Parameter,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration => $value->collation !== null && $value->collation->name === null,
            default => false,
        };
    }
}
