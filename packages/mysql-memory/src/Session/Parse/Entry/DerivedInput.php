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
     * Resolves MySQL 5.6 table dependencies before unfinished DML or query syntax.
     * Validated names come from Semantics; the plan never receives parser nodes.
     */
    public function prefix(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '' || $session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $statement);
        if (!in_array(strtoupper($tokens[0]->text ?? ''), ['SELECT', 'INSERT', 'REPLACE', 'UPDATE', 'DELETE'], true)) {
            return null;
        }
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->dependencies($parser, $statement, $end, $semantics->profile()) as [$offset, $name]) {
            if ($name->schema === null) {
                return (new CacheOptions())->literal(QueryError::NoDatabase->error(), $offset, $statement, $session);
            }
        }
        return null;
    }

    /**
     * Resolves an outer DELETE target before a later input-operand refusal.
     * A complete target refusal already retains its typed dependencies; reuse
     * their resolved error instead of overriding it from the source prefix.
     */
    public function refusal(\SqlSemantics\Construction\RejectedInput $failure, string $statement, Session $session, ?SqlError $resolved = null): ?SqlError
    {

        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate) {
            return $resolved ?? (new \MySqlMemory\Program\Write\UpdateUse())->error($failure->problem, $session);
        }
        return $this->targets($statement, $session, $failure->end) ?? $this->written($statement, $session, $failure->end);
    }

    /**
     * Answers a missing database on outer DELETE targets before relation lowering.
     */
    public function targets(string $statement, Session $session, int $end = PHP_INT_MAX): ?SqlError
    {
        if ($session->variables->database !== '') {
            return null;
        }
        try {
            $names = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->deleted($session->semantics()->parser()->parse($statement), $session->semantics()->profile(), $end);
        } catch (SourceException) {
            return null;
        }

        return array_filter($names, static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): bool => $name->schema === null) === [] ? null : QueryError::NoDatabase->error();
    }

    /**
     * Resolves the completed write head before interpreting its refused values.
     */
    public function written(string $statement, Session $session, int $end): ?SqlError
    {
        if ($session->variables->database !== '') {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $statement, strlen($statement), $semantics->profile());
            if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $notice[1]->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax) !== []) {
                return null;
            }
        }
        try {
            $names = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->written($semantics->parser()->parse($statement), $semantics->profile(), $semantics->context(session: $session->resolution())->relationNames, $end);
        } catch (SourceException) {
            return null;
        }
        return array_filter($names, static fn (\SqlSemantics\Statement\Identifier\QualifiedName $name): bool => $name->schema === null) === [] ? null : QueryError::NoDatabase->error();
    }

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
