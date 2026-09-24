<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Configuration\Replication\GtidBoundary;
use SqlSemantics\Model\Configuration\Replication\GtidUntil;
use SqlSemantics\Model\Configuration\Replication\RelayPosition;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Configuration\Replication\UntilCondition;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the UNTIL clause of START REPLICA into one stop point, as the server does: a later coordinate replaces an earlier one.
 * @visibility SqlSemantics
 */
final class Stops
{
    /**
     * Returns null without an UNTIL clause and diagnoses incomplete or mixed stop points.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $node): ?UntilCondition
    {
        $clause = Tree::outer($node, ['slave_until', 'opt_replica_until'])[0] ?? null;
        if ($clause === null || !Tree::hasTokens($clause)) {
            return null;
        }
        $coordinates = [];
        foreach (Tree::outer($clause, ['master_file_def', 'source_file_def']) as $definition) {
            $tokens = $definition->tokens();
            $coordinates[self::coordinate(strtoupper($tokens[0]->text))] = Literals::text($tokens[count($tokens) - 1]);
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $clause->tokens());
        $gtids = GtidUntil::tryFrom($words[1] ?? '');
        try {
            return self::condition($coordinates, $gtids, $gtids === null ? null : Literals::text($clause->tokens()[3] ?? $clause->tokens()[0]), in_array('SQL_AFTER_MTS_GAPS', $words, true)) ?? throw new InvalidSql(InputViolation::ReplicaUntil, $clause);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ReplicationOption, $clause, $error);
        }
    }

    /**
     * Returns the one stop point the coordinates describe, or null when they are incomplete or mixed.
     * @param array<string, Literal> $coordinates
     * @throws InvalidStructure
     */
    public static function condition(array $coordinates, ?GtidUntil $gtids, ?Literal $set, bool $gaps): ?UntilCondition
    {
        $source = isset($coordinates['source-file']) || isset($coordinates['source-position']);
        $relay = isset($coordinates['relay-file']) || isset($coordinates['relay-position']);
        return match (true) {
            $gaps => $coordinates === [] && $gtids === null ? new GapsClosed() : null,
            $gtids !== null && $set !== null => $coordinates === [] ? new GtidBoundary($gtids, $set) : null,
            $source && !$relay && isset($coordinates['source-file'], $coordinates['source-position']) => new SourcePosition($coordinates['source-file'], $coordinates['source-position']),
            $relay && !$source && isset($coordinates['relay-file'], $coordinates['relay-position']) => new RelayPosition($coordinates['relay-file'], $coordinates['relay-position']),
            default => null,
        };
    }

    /**
     * Names the coordinate a log keyword sets; MASTER_ and SOURCE_ spell the same coordinate.
     */
    public static function coordinate(string $keyword): string
    {
        return match ($keyword) {
            'MASTER_LOG_FILE', 'SOURCE_LOG_FILE' => 'source-file',
            'MASTER_LOG_POS', 'SOURCE_LOG_POS' => 'source-position',
            'RELAY_LOG_FILE' => 'relay-file',
            default => 'relay-position',
        };
    }
}
