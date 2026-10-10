<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Validation\ValueGraph;

/**
 * Resolves a partition expression and records restrictions checked before the table is opened.
 *
 * Variables, clocks, stored calls and functions that read or change session or external state
 * are rejected at this stage. Other restrictions on the permitted partition functions are
 * checked after table resolution; even a constant or VERSION() reaches that later stage.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-limitations.html.
 * Verified with ALTER TABLE of an absent table on MySQL 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PartitionExpressions
{
    /**
     * Calls refused before table lookup, as observed through the public SQL interface.
     */
    public const EARLY = ['USER', 'CURRENT_USER', 'SESSION_USER', 'SYSTEM_USER', 'DATABASE', 'SCHEMA', 'CONNECTION_ID', 'FOUND_ROWS', 'LAST_INSERT_ID', 'ROW_COUNT', 'RAND', 'RANDOM_BYTES', 'UUID', 'UUID_SHORT', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS', 'LOAD_FILE'];

    /**
     * Derives the expression, preserving its warnings before an aborting partition diagnostic.
     */
    public function derive(Scalar $expression, Derivation $derivation, Environment $scope): void
    {
        $before = count($derivation->facts()->diagnostics);
        $derivation->scalar($expression, $scope);
        foreach (array_slice($derivation->facts()->diagnostics, $before) as $problem) {
            if ($problem instanceof WrongArgumentCount || $problem instanceof NamedArgument || $problem instanceof ReservedFunction || $problem instanceof UnknownSystemVariable || $problem instanceof UnstructuredVariable) {
                return;
            }
        }
        if ($this->forbidden($expression, $derivation->context->profile->grammar)) {
            $problem = new InvalidPartitionExpression($expression);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
    }

    /**
     * Tells whether any part requires the early partition-expression refusal.
     */
    public function forbidden(Scalar $expression, GrammarRelease $release): bool
    {
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']);
        foreach ($graph->objects($expression) as $part) {
            if ($part instanceof ClockCall || $part instanceof UserVariable || $part instanceof SystemVariable || $part instanceof VariableAssignment) {
                return true;
            }
            if ($part instanceof FunctionCall && ($part->schema !== null || !(new NativeFunctions())->exists($release, $part->name->value))) {
                return true;
            }
            $name = $part instanceof FunctionCall ? strtoupper($part->name->value) : ($part instanceof KeywordCall ? $part->function->value : null);
            if ($name !== null && (in_array($name, self::EARLY, true) || ($name === 'UNIX_TIMESTAMP' && $part instanceof FunctionCall && $part->arguments === []))) {
                return true;
            }
        }

        return false;
    }
}
