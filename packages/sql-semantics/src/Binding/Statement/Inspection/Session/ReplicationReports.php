<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Inspection\ShowRequest;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Inspection\Replication;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies SHOW requests about binary logs, relay logs, and replicas; synonymous spellings bind to one request.
 * @visibility SqlSemantics
 */
final class ReplicationReports
{
    /**
     * Routes by the keywords following SHOW.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        $form = $request->form;
        return match ($request->word(0) . ' ' . $request->word(1)) {
            'BINARY LOGS', 'MASTER LOGS' => new Replication\ShowBinaryLogsStatement($origin),
            'BINARY LOG', 'MASTER STATUS' => new Replication\ShowBinaryLogStatusStatement($origin),
            'BINLOG EVENTS' => new Replication\ShowBinaryLogEventsStatement($origin, self::log($form, $context), self::position($form), RowWindows::read($form, $context)),
            'RELAYLOG EVENTS' => new Replication\ShowRelayLogEventsStatement($origin, self::log($form, $context), self::position($form), RowWindows::read($form, $context), self::channel($form, $context)),
            'REPLICA STATUS' => new Replication\ShowReplicaStatusStatement($origin, ReplicationVocabulary::Current, self::channel($form, $context)),
            'SLAVE STATUS' => new Replication\ShowReplicaStatusStatement($origin, ReplicationVocabulary::Legacy, self::channel($form, $context)),
            'REPLICAS ' => new Replication\ShowReplicasStatement($origin),
            'SLAVE HOSTS' => new Replication\ShowReplicasStatement($origin, ReplicationVocabulary::Legacy),
            default => null,
        };
    }

    /**
     * Reads the IN log file name; null when the listing starts with the first log.
     */
    public static function log(Node $form, QueryContext $context): ?string
    {
        $clause = Tree::child($form, ['opt_binlog_in', 'binlog_in']);
        if ($clause === null) {
            return null;
        }
        $tokens = $clause->tokens();
        return MySqlNames::read($tokens[count($tokens) - 1], $context->tables->identifiers);
    }

    /**
     * Reads the FROM position as written; null when the listing starts at the first event.
     */
    public static function position(Node $form): ?Literal
    {
        $clause = Tree::child($form, ['binlog_from']);
        if ($clause === null) {
            return null;
        }
        $tokens = $clause->tokens();
        $position = (new LiteralBinder(Dialect::MySql))->bind($tokens[count($tokens) - 1]);
        return $position instanceof Literal ? $position : Tree::invalid($clause, 'log event position');
    }

    /**
     * Reads the FOR CHANNEL name; null selects the default channel, or every channel for a status request.
     * @throws InvalidSql
     */
    public static function channel(Node $form, QueryContext $context): ?string
    {
        $clause = Tree::child($form, ['opt_channel']);
        if ($clause === null) {
            return null;
        }
        $tokens = $clause->tokens();
        $channel = MySqlNames::read($tokens[count($tokens) - 1], $context->tables->identifiers);
        if (str_contains($channel, "\n")) {
            throw new InvalidSql(InputViolation::ChannelName, $clause);
        }
        return $channel;
    }
}
