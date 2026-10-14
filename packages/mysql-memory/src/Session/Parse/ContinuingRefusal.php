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
 * warnings and refusals. It never reaches contextualization or execution.
 * The input remains confined to this reading boundary; no rejected expression
 * is fabricated to carry its diagnostics into a plan.
 *
 * @visibility MySqlMemory
 */
final class ContinuingRefusal
{
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
        foreach ($warnings->before($parser->tokenize($statement), $statement, $end, $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $error = (new Errors())->error($failure->problem, $session);
        $append = false;
        try {
            (new Syntax())->debugOnly($parser->parse($statement), $statement);
        } catch (SqlError $debug) {
            $error = $debug;
            $append = true;
        }

        return $warnings->recorded(array_column($notices, 1), $error, $session, $append);
    }
}
