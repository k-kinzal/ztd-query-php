<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;

/**
 * Preserves completed grammar conditions when lowering cannot publish an operand.
 *
 * A continuing input refusal, such as AT LOCAL, still allows later grammar
 * warnings and refusals. Contextual operand failures follow completed grammar
 * conditions. Neither publishes a rejected expression for execution.
 * The input remains confined to this reading boundary; no rejected expression
 * is fabricated to carry its diagnostics into a plan.
 *
 * @visibility MySqlMemory
 */
final class ContinuingRefusal
{
    /**
     * Keeps continuing semantic conditions before a later legacy syntax refusal.
     * A modifier conflict retains the legacy parser's stopping behavior.
     */
    public function unfinished(SqlError $error, string $statement, int $end, Session $session): ?SqlError
    {
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure) === []) {
            return null;
        }
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $statement);
        $modifiers = new Modifiers($tokens, $session->settings()->release());
        [$operators, $conflict] = $modifiers->scan($end, -1);
        if ($conflict !== null) {
            $notices = array_values(array_filter($notices, static fn (array $notice): bool => $notice[0] <= ($modifiers->stoppedAt ?? $end)));
            $error = (new \MySqlMemory\Session\CacheOptions())->refusal($conflict);
        }
        $notices = (new ModernWarnings())->merge($notices, $operators, $tokens);
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return (new ModernWarnings())->recorded(array_column($notices, 1), $error, $session);
    }

    /**
     * Records the complete grammar observation, including a debug-build refusal.
     *
     * Without a current database, an unqualified table name written before the refused
     * operand is refused first (ER_NO_DB_ERROR), also before a legacy modifier conflict the
     * token scan finds (verified on live 5.7.44: `(SELECT 1 FROM t LIMIT a) UNION (SELECT SQL_CACHE 2)` -> 1046).
     */
    public function error(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $end = strlen($statement);
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        $warnings = new ModernWarnings();
        $tokens = $parser->tokenize($statement);
        $prefix = ($session->settings()->legacy() ? (new CommandPrefix())->before($tokens, $statement, $failure->end, $session) : null)
            ?? (new Entry\ParserInput())->before($statement, $end, $session);
        if ($prefix !== null) {
            return $prefix;
        }
        $error = (new Errors())->error($failure->problem, $session);
        $tree = $parser->parse($statement);
        $implicit = $session->variables->database === '' && (new Entry\ImplicitTables())->before($tree, $failure->end, $session->settings()->legacy());
        if ($session->settings()->legacy()) {
            $modifiers = new Modifiers($tokens, $session->settings()->release());
            [$operators, $conflict] = $modifiers->scan($end, $end);
            if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 && ($error->error === \MySqlMemory\Error\Family\QueryError::NoDatabase || array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $notice[1]->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax) !== [])) {
                $conflict = $modifiers->conflict;
            }
            if ($conflict !== null && !$implicit) {
                return (new \MySqlMemory\Session\CacheOptions())->recorded($operators, $conflict, $error, $session);
            }
        } else {
            $operators = $warnings->before($tokens, $statement, $end, $parser);
        }
        $notices = (new ModernWarnings())->merge($notices, $operators, $tokens);
        $notices = [...$notices, ...(new Entry\ProgramInput())->completed($failure->problem, $session), ...(new Entry\Query\Notices())->completed($failure, $session)];
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $contextual = true;
        $recipients = (new Entry\Account\Recipients())->notes($failure, $tree, $session);
        if ($recipients !== []) {
            $notices = [...$notices, ...$recipients];
            usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        }
        $target = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->created($tree, $session->semantics()->profile());
        if ($implicit || ($session->variables->database === '' && $target !== null && $target->schema === null)) {
            $error = \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
            $contextual = false;
        }
        $append = array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure) === [];
        try {
            (new Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->debugOnly($tree, $statement);
        } catch (SqlError $debug) {
            $error = $debug;
            $append = true;
            $contextual = false;
        }
        if ($append && $contextual) {
            $notices[] = [$end, new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, true)];
            $append = false;
        }

        return $warnings->recorded(array_column($notices, 1), $error, $session, $append);
    }
}
