<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Configuration\Administration\ResetTarget;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the MySQL RESET option lists for binary logs, replicas and the query cache.
 * @visibility SqlSemantics
 */
final class Resets
{
    /**
     * Returns null when the statement has no server reset options.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): ?ResetServerStatement
    {
        $options = Tree::outer($node, ['reset_option']);
        if ($options === []) {
            return null;
        }
        return new ResetServerStatement($origin, array_map(static fn (Node $option): ResetTarget => self::target($option, $scope), $options));
    }

    /**
     * Reads one reset option; MASTER and BINARY LOGS AND GTIDS are the same target, as are SLAVE and REPLICA.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function target(Node $option, Scope $scope): ResetTarget
    {
        $word = strtoupper($option->tokens()[0]->text ?? '');
        if ($word === 'QUERY') {
            return new QueryCacheReset();
        }
        if ($word === 'SLAVE' || $word === 'REPLICA') {
            $all = in_array('ALL', array_map(static fn ($token): string => strtoupper($token->text), $option->tokens()), true);
            return new ReplicaReset($all, Channels::read($option, $scope->identifiers));
        }
        if ($word !== 'MASTER' && $word !== 'BINARY') {
            throw new UnclassifiedSql('Unclassified RESET option: ' . $option->toString());
        }
        $index = Tree::outer($option, ['real_ulonglong_num'])[0] ?? null;
        try {
            return new BinaryLogReset($index === null ? null : Literals::text($index));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::BinaryLogIndex, $option, $error);
        }
    }
}
