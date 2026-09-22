<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Write\Policy;

/**
 * Reads insertion modifiers from the operation's header and override clause.
 *
 * @visibility SqlSemantics
 */
final class InsertionPolicyBinder
{
    /**
     * Returns a policy whose native fields belong to the statement dialect.
     */
    public static function bind(Node $source, Dialect $dialect): Policy\InsertPolicy
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        $into = array_search('INTO', $words, true);
        $prefix = $into === false ? $words : array_slice($words, 0, $into);
        if ($dialect === Dialect::Sqlite) {
            $command = \SqlSemantics\Ast\Tree::child($source, ['insert_cmd']) ?? $source;
            $conflict = \SqlSemantics\Ast\Tree::child($command, ['orconf']);
            $resolution = $conflict === null ? null : \SqlSemantics\Ast\Tree::child($conflict, ['resolvetype']);
            return new Policy\SqliteInsertion($resolution === null ? Policy\ConstraintResponse::Default : Policy\ConstraintResponse::from(strtoupper(\SqlSemantics\Ast\Tree::text($resolution))));
        }
        if ($dialect === Dialect::MySql) {
            $scheduling = Policy\Scheduling::Default;
            foreach ($prefix as $word) {
                $scheduling = Policy\Scheduling::tryFrom($word) ?? $scheduling;
            }
            return new Policy\MySqlInsertion($scheduling, in_array('IGNORE', $prefix, true));
        }
        $input = \SqlSemantics\Ast\Tree::child($source, ['insert_rest']) ?? $source;
        $overriding = \SqlSemantics\Ast\Tree::child($input, ['override_kind']);
        return new Policy\PostgreSqlInsertion($overriding === null ? Policy\IdentityOverride::Default : Policy\IdentityOverride::from(strtoupper(\SqlSemantics\Ast\Tree::text($overriding))));
    }
}
