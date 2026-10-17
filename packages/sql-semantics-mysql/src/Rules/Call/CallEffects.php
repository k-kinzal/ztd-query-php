<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Node;

/**
 * Describes calls and variable accesses that prevent retaining a subquery's result.
 *
 * This is independent of column correlation. RAND with a constant seed still advances
 * between reads, and user variables can change elsewhere in the statement. A statement
 * clock or a pure function of constants does not have those effects. Stored and loadable
 * calls retain their boundary because their bodies are not analyzed here.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain-output.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CallEffects
{
    /**
     * Calls whose result or side effects depend on each evaluation.
     */
    public const PER_READ_FUNCTIONS = ['RAND', 'UUID', 'UUID_SHORT', 'SYSDATE', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'RANDOM_BYTES', 'VALUES', 'NEXTVAL'];

    /**
     * Tells whether a known call varies for each read, including LAST_INSERT_ID with an argument.
     */
    public function perRead(string $name, int $arguments): bool
    {
        $name = strtoupper($name);

        return in_array($name, self::PER_READ_FUNCTIONS, true) || ($name === 'LAST_INSERT_ID' && $arguments > 0);
    }

    /**
     * Tells whether an occurrence prevents caching, without inspecting its child expressions.
     */
    public function uncacheable(Node $node, GrammarRelease $release): bool
    {
        return match (true) {
            $node instanceof UserVariable, $node instanceof VariableAssignment => true,
            $node instanceof ClockCall => $node->clock === Clock::SystemDate,
            $node instanceof FunctionCall => $node->schema !== null || !(new NativeFunctions())->exists($release, $node->name->value) || $this->perRead($node->name->value, count($node->arguments)),
            default => false,
        };
    }
}
