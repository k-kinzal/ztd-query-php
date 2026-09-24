<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Configuration\Replication\ReplicationCredential;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds START and STOP for replica threads and for group replication, in both the SLAVE and REPLICA vocabularies.
 * @visibility SqlSemantics
 */
final class Replicas
{
    /**
     * Distinguishes the operation by its first keywords.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), array_slice($node->tokens(), 0, 2));
        $start = $words[0] === 'START';
        $channel = Channels::read($node, $context->tables->identifiers);
        try {
            if (($words[1] ?? '') === 'GROUP_REPLICATION') {
                return $start ? new Statement\StartGroupReplicationStatement($origin, self::credentials($node)) : new Statement\StopGroupReplicationStatement($origin);
            }
            if (!$start) {
                return new Statement\StopReplicaStatement($origin, self::threads($node), $channel);
            }
            return new Statement\StartReplicaStatement($origin, self::threads($node), Stops::read($node), self::credentials($node), $channel);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ReplicaCredentials, $node, $error);
        }
    }

    /**
     * Reads the thread keywords; RELAY_THREAD is IO_THREAD.
     * @return list<ReplicaThread>
     */
    public static function threads(Node $node): array
    {
        return array_map(static fn (Node $option): ReplicaThread => strtoupper(Tree::text($option)) === 'SQL_THREAD' ? ReplicaThread::Applier : ReplicaThread::Receiver, Tree::outer($node, ['slave_thread_option', 'replica_thread_option']));
    }

    /**
     * Reads USER, PASSWORD, DEFAULT_AUTH and PLUGIN_DIR in request order.
     * @return list<ReplicationCredential>
     * @throws UnclassifiedSql
     */
    public static function credentials(Node $node): array
    {
        $credentials = [];
        foreach (Tree::outer($node, ['slave_user_name_opt', 'slave_user_pass_opt', 'slave_plugin_auth_opt', 'slave_plugin_dir_opt', 'opt_user_option', 'opt_password_option', 'opt_default_auth_option', 'opt_plugin_dir_option', 'group_replication_start_option']) as $option) {
            $tokens = $option->tokens();
            if ($tokens === []) {
                continue;
            }
            $credentials[] = new ReplicationCredential(CredentialOption::from(strtoupper($tokens[0]->text)), Literals::text($tokens[count($tokens) - 1]));
        }
        return $credentials;
    }
}
