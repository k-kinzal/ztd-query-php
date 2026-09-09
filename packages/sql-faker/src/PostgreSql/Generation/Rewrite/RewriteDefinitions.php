<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\TerminalMappingRule;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;

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
            new WindowFrameRule(),
            new RelationNameRule(),
            new PublicationObjectRule(),
            new OverlapsArgumentsRule(),
            new TimeZoneIntervalRule(),
            new LimitOffsetRule(),
            new FetchWithTiesRule(),
            new HashPartitionBoundRule(),
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
