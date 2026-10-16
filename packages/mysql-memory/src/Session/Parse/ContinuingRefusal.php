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
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile());
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
        foreach ($operators as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return (new ModernWarnings())->recorded(array_column($notices, 1), $error, $session);
    }

    /**
     * Records the complete grammar observation, including a debug-build refusal.
     */
    public function error(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $end = strlen($statement);
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile());
        $warnings = new ModernWarnings();
        $tokens = $parser->tokenize($statement);
        $error = (new Errors())->error($failure->problem, $session);
        if ($session->settings()->legacy()) {
            [$operators, $conflict] = (new Modifiers($tokens, $session->settings()->release()))->scan($end, $end);
            if ($conflict !== null) {
                return (new \MySqlMemory\Session\CacheOptions())->recorded($operators, $conflict, $error, $session);
            }
        } else {
            $operators = $warnings->before($tokens, $statement, $end, $parser);
        }
        foreach ($operators as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        $notices = [...$notices, ...(new Entry\ProgramInput())->completed($failure->problem, $session)];
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $target = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->created($parser->parse($statement), $session->semantics()->profile());
        if ($target !== null && $target->schema === null && $session->variables->database === '') {
            $error = \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
        $append = array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure) === [];
        try {
            (new Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->debugOnly($parser->parse($statement), $statement);
        } catch (SqlError $debug) {
            $error = $debug;
            $append = true;
        }

        return $warnings->recorded(array_column($notices, 1), $error, $session, $append);
    }
}
