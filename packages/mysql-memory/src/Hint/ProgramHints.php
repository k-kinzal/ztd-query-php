<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Reads stored instructions' hint conditions without resolving their table data.
 * Each instruction has its own query-block namespace. Resource-group placement
 * is reported once after the definition's other hint conditions. Observed on
 * MySQL 8.0 and 8.4, including unreachable instructions and repeated hints.
 *
 * @visibility MySqlMemory
 */
final class ProgramHints
{
    /**
     * Returns declaration-time conditions from the retained instruction graph.
     *
     * @return list<array{int, string}>
     */
    public function warnings(CreateProcedure|CreateFunction|CreateTrigger $definition, Session $session): array
    {
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return [];
        }
        $warnings = [];
        $resource = false;
        foreach ($this->blocks($definition->body, $session) as $blocks) {
            $registration = (new Registration($blocks, $session->variables->catalog, new Printer($session->modes()->has('ANSI_QUOTES')), true))->register();
            foreach ($registration->warnings as [$code, $message]) {
                if ($code === StatementError::HintNotSupported->number()) {
                    $resource = true;
                } else {
                    $warnings[] = [$code, $message];
                }
            }
        }
        if ($resource) {
            $warnings[] = [StatementError::HintNotSupported->number(), StatementError::HintNotSupported->message('Subquery or Stored procedure or Trigger')];
        }
        return $warnings;
    }

    /**
     * Visits SQL instruction boundaries, including expressions of flow statements.
     *
     * @return list<Blocks>
     */
    public function blocks(Node $body, Session $session): array
    {
        $pending = [$body];
        $found = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_reverse($value));
            } elseif ($value instanceof Node) {
                $blocks = (new Blocks($session->instance->dictionary, $session->variables->database))->read($value);
                if ($value instanceof Scalar) {
                    $blocks->open(false);
                    $blocks->resolved = [1, ...$blocks->subqueries($value)];
                    $blocks->contextualized[] = 1;
                }
                if ($blocks->blocks !== []) {
                    $found[] = $blocks;
                } else {
                    array_push($pending, ...array_reverse(array_values(get_object_vars($value))));
                }
            }
        }
        return $found;
    }
}
