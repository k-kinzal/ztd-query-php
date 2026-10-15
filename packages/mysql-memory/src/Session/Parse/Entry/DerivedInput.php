<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\CacheOptions;
use MySqlMemory\Session\Parse\Clashes;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;

/**
 * Preserves legacy relation-registration conditions before a refused derived factor.
 * Database selection and repeated names precede the factor's missing alias.
 * This inspection is confined to the input boundary and publishes no parser objects.
 *
 * @visibility MySqlMemory
 */
final class DerivedInput
{
    /**
     * Answers the earliest relation condition before an input refusal boundary.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        if (!$session->settings()->legacy()) {
            return null;
        }
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $found = [];
        try {
            $parser->parse($statement, function (Node $node) use (&$found, $end, $session): void {
                foreach ($this->conditions($node, $end, $session->variables->database) as [$offset, $error]) {
                    $found[$offset] ??= $error;
                }
            });
        } catch (SourceException) {
            // Only completed relations before the original refusal contribute conditions.
        }
        ksort($found);
        $offset = array_key_first($found);

        return $offset === null ? null : (new CacheOptions())->literal($found[$offset], $offset, $statement, $session);
    }

    /**
     * Collects missing databases and duplicate aliases from completed grammar operands.
     *
     * @return list<array{int, SqlError}> Conditions with their input offsets
     */
    public function conditions(Node $node, int $end, string $database): array
    {
        if ($node->name === 'table_ident' && $database === '') {
            $tokens = $node->tokens();
            $last = $tokens[count($tokens) - 1] ?? null;

            return count($tokens) < 3 && $last !== null && $last->end() <= $end ? [[$last->end(), QueryError::NoDatabase->error()]] : [];
        }
        if ($node->name !== 'join_table_list') {
            return [];
        }
        $seen = [];
        foreach ((new Clashes())->factors($node, $database) as [$schema, $alias, $offset]) {
            if ($offset >= $end) {
                continue;
            }
            $key = $schema . "\0" . $alias;
            if (isset($seen[$key])) {
                return [[$offset, QueryError::NonUniqueTable->error($alias)]];
            }
            $seen[$key] = true;
        }

        return [];
    }
}
