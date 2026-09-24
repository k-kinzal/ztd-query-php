<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the FOR CHANNEL operand of replication commands.
 * @visibility SqlSemantics
 */
final class Channels
{
    /**
     * Returns the decoded channel name of the first FOR CHANNEL clause inside the node, or null when none is written.
     * @throws InvalidSql
     */
    public static function read(Node $owner, Identifiers $identifiers): ?string
    {
        $clause = Tree::outer($owner, ['opt_channel'])[0] ?? null;
        $tokens = $clause?->tokens() ?? [];
        if ($clause === null || $tokens === []) {
            return null;
        }
        $channel = MySqlNames::read($tokens[count($tokens) - 1], $identifiers);
        if (str_contains($channel, "\n")) {
            throw new InvalidSql(InputViolation::ChannelName, $clause);
        }
        return $channel;
    }
}
