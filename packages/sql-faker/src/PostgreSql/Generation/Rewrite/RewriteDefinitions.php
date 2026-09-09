<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\TerminalMappingRule;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Column\IdentityOptionRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\FunctionNameRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\IntoClauseRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule;

/**
 * Declares PostgreSQL structural rules by their original grammar scope.
 */
final class RewriteDefinitions
{
    /**
     * UPDATE set_target_list is not a relation-option list and receives no generic SET normalization.
     */
    public function create(): TokenRewriter
    {
        return new TokenRewriter(
            new OperatorArgumentsRule(),
            new CopySourceRule(),
            new FunctionNameRule(),
            new IndirectionStarRule(),
            new ConstraintAttributesRule(),
            new GeneratedColumnRule(),
            new WindowFrameRule(),
            new RelationNameRule(),
            new AnyRelationNameRule(),
            new PublicationObjectRule(),
            new OverlapsArgumentsRule(),
            new TimeZoneIntervalRule(),
            new LimitOffsetRule(),
            new FetchWithTiesRule(),
            new SelectOptionsRule(),
            new TableFunctionRule(),
            new IdentityOptionRule(),
            new IntoClauseRule(),
            new HashPartitionBoundRule(),
            new UniqueOptionRule('columnDef', 'ColConstraint', ['COLLATE' => 'collation'], null, 'gram.y:SplitColQualList'),
            new UniqueOptionRule('columnOptions', 'ColConstraint', ['COLLATE' => 'collation'], null, 'gram.y:SplitColQualList'),
            new UniqueOptionRule('CreateDomainStmt', 'ColConstraint', ['COLLATE' => 'collation'], null, 'gram.y:SplitColQualList'),
            new UniqueOptionRule('CreateTrigStmt', 'TriggerOneEvent', [
                'INSERT' => 'insert', 'DELETE_P' => 'delete', 'UPDATE' => 'update', 'TRUNCATE' => 'truncate',
            ], 'OR', 'gram.y:TriggerEvents'),
            new UniqueOptionRule('xmltable_column_el', 'xmltable_column_option_el', [
                'DEFAULT' => 'default', 'IDENT' => 'path', 'NOT' => 'null', 'NULL_P' => 'null',
            ], null, 'gram.y:xmltable_column_el'),
            new TerminalMappingRule('RowSecurityDefaultPermissive', 'IDENT', 'POLICY_MODE', 'gram.y:RowSecurityDefaultPermissive'),
            new TerminalMappingRule('AlterOptRoleElem', 'IDENT', 'ROLE_OPTION', 'gram.y:AlterOptRoleElem'),
            new TerminalMappingRule('xmltable_column_option_el', 'IDENT', 'PATH', 'gram.y:xmltable_column_el'),
            new ExpressionGroupingRule(['a_expr', 'b_expr'], 'gram.y:a_expr/b_expr:c_expr:parenthesized-operands'),
            new LookaheadRule(),
        );
    }
}
