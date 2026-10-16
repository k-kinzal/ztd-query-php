<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;

/**
 * Formats a declaration-kind refusal using its typed header and current database.
 *
 * @visibility MySqlMemory
 */
final class ProgramInput
{
    /**
     * Preserves definer and default-database conditions before the body's condition.
     */
    public function error(RefusedProgramBody $refusal, Session $session): SqlError
    {
        $diagnostics = new \MySqlMemory\Session\Diagnostics();
        try {
            (new Names($session->settings()->release()))->check([$refusal->definer]);
            if ($refusal->parameters !== null) {
                (new \MySqlMemory\Command\Program\RoutineCommand())->types($refusal, $session, new \MySqlMemory\Evaluation\Context($session->modes(), $diagnostics, $session->variables, microtime(true)));
            }
            $database = $refusal->name->schema->value ?? $session->variables->database;
            if ($database === '') {
                throw QueryError::NoDatabase->error();
            }
            if ($refusal->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch) {
                return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($refusal->problem->offset ?? strlen($session->text), $session->text, $session->following, new RuntimeException($refusal->problem->message()), true, $session->semantics()->parser());
            }
            $error = $refusal->problem->rule === ProgramRule::MissingReturn
                ? ProgramError::MissingReturn->error($database . '.' . $refusal->name->name->value)
                : (new ProgramErrors())->error($refusal->problem, $session->settings()->release());
        } catch (SqlError $error) {
            // A preceding header condition replaces the body condition.
        }
        return $error;
    }

    /**
     * Preserves type-validation notes at their completion boundaries.
     *
     * @return list<array{int, InputNote}>
     */
    public function notes(RefusedProgramBody $refusal, Session $session): array
    {
        $types = new \MySqlMemory\Command\Program\RoutineDeclarations();
        $notes = [];
        foreach ($types->items($refusal) as $index => $type) {
            $diagnostics = new \MySqlMemory\Session\Diagnostics();
            $diagnostics->limit = PHP_INT_MAX;
            $context = new \MySqlMemory\Evaluation\Context($session->modes(), $diagnostics, $session->variables, microtime(true));
            try {
                $types->check($refusal->name, [$type], $session, $context);
            } catch (SqlError) {
                break;
            }
            foreach ($diagnostics->conditions as $condition) {
                $notes[] = [$refusal->typeEnds[$index] ?? 0, new InputNote(\MySqlMemory\Error\ErrorNumbers::from($condition[1]), $condition[2])];
            }
        }
        return $notes;
    }

    /**
     * Stops input notices where a preceding header error prevents further reading.
     */
    public function boundary(RefusedProgramBody $refusal, Session $session, int $end): int
    {
        try {
            (new Names($session->settings()->release()))->check([$refusal->definer]);
            \MySqlMemory\Command\Program\ProgramSource::database($refusal->name->schema, $session);
        } catch (SqlError) {
            return $refusal->nameEnd;
        }
        $types = new \MySqlMemory\Command\Program\RoutineDeclarations();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), new \MySqlMemory\Session\Diagnostics(), $session->variables, microtime(true));
        foreach ($types->items($refusal) as $index => $type) {
            try {
                $types->check($refusal->name, [$type], $session, $context);
            } catch (SqlError) {
                return $refusal->typeEnds[$index] ?? $end;
            }
        }
        return $end;
    }

    /**
     * Keeps other operand refusals unchanged and stops a program at its first header error.
     */
    public function stopped(\SqlSemantics\Construction\RejectedInput $failure, Session $session): \SqlSemantics\Construction\RejectedInput
    {
        if (!$failure->problem instanceof RefusedProgramBody) {
            return $failure;
        }
        $end = $this->boundary($failure->problem, $session, $failure->end);
        return new \SqlSemantics\Construction\RejectedInput($failure->problem, $end, $end < $failure->end);
    }

    /**
     * Adds typed declaration notes only when a program body was refused.
     *
     * @return list<array{int, InputNote}>
     */
    public function completed(\SqlSemantics\Statement\Fact\Diagnostic $problem, Session $session): array
    {
        return $problem instanceof RefusedProgramBody ? $this->notes($problem, $session) : [];
    }
}
