<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
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
     * Interleaves typed header conditions with retained body notices without reading SQL.
     *
     * @param array<int, \SqlSemantics\Statement\Fact\Warning> $warnings The ordered statement notices
     * @return list<\SqlSemantics\Statement\Fact\Warning|InputNote|SqlError>
     */
    public function retained(\SqlSemantics\Platform\MySql\Statement\Statement $statement, array $warnings, Session $session): array
    {
        $refusal = $this->refusal($warnings);
        if ($refusal === null) {
            return array_values($warnings);
        }
        $inputs = array_filter([$refusal->parameters, $refusal->returns, $refusal->collation], static fn ($node): bool => $node !== null);
        $ranks = [];
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->inputs(array_values($inputs), childrenFirst: true) as $index => $node) {
            $ranks[spl_object_id($node)] = $index + 1;
        }
        $located = $statement->source() !== null;
        $positions = [];
        foreach ($statement->statementInformation()->notices as $notice) {
            $positions[spl_object_id($notice->warning)] = $located ? ($notice->inputSource->offset ?? PHP_INT_MAX) : ($ranks[spl_object_id($notice->subject ?? $statement)] ?? PHP_INT_MAX);
        }
        $ordered = array_map(static fn ($warning): array => [$positions[spl_object_id($warning)] ?? PHP_INT_MAX, $warning], array_values($warnings));
        foreach ($this->header($refusal, $session) as [$index, $notice]) {
            $type = $index === null ? null : ((new \MySqlMemory\Command\Program\RoutineDeclarations())->items($refusal)[$index] ?? null);
            $completion = $type === null ? null : ($type[1] ?? $type[0]);
            $position = $located ? ($index === null ? $refusal->nameEnd : ($refusal->typeEnds[$index] ?? 0)) : ($completion === null ? 0 : ($ranks[spl_object_id($completion)] ?? 0));
            $ordered[] = [$position, $notice];
        }
        usort($ordered, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        return array_column($ordered, 1);
    }

    /**
     * Finds the typed definition header attached to its first body condition.
     *
     * @param array<int, \SqlSemantics\Statement\Fact\Warning> $warnings The statement's ordered notices
     */
    public function refusal(array $warnings): ?RefusedProgramBody
    {
        foreach ($warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof RefusedProgramBody) {
                return $warning->problem;
            }
        }
        return null;
    }

    /**
     * Checks the declaration name and each parameter or return type before its body.
     *
     * @return list<array{int|null, InputNote|SqlError}> The parameter index, or null for the name
     */
    public function header(RefusedProgramBody $refusal, Session $session): array
    {
        try {
            (new Names($session->settings()->release()))->check([$refusal->definer]);
            \MySqlMemory\Command\Program\ProgramSource::database($refusal->name->schema, $session);
        } catch (SqlError $error) {
            return [[null, $error]];
        }
        $types = new \MySqlMemory\Command\Program\RoutineDeclarations();
        $notices = [];
        foreach ($types->items($refusal) as $index => $type) {
            $diagnostics = new \MySqlMemory\Session\Diagnostics();
            $diagnostics->limit = PHP_INT_MAX;
            try {
                $types->check($refusal->name, [$type], $session, new \MySqlMemory\Evaluation\Context($session->modes(), $diagnostics, $session->variables, microtime(true)));
            } catch (SqlError $error) {
                $notices[] = [$index, $error];
                return $notices;
            }
            foreach ($diagnostics->conditions as $condition) {
                $notices[] = [$index, new InputNote(\MySqlMemory\Error\ErrorNumbers::from($condition[1]), $condition[2])];
            }
        }
        return $notices;
    }

    /**
     * Preserves a routine's completed header notes before an unprepared marker.
     * The full input has already parsed; only the refused declaration's typed
     * header is inspected. Later body conditions do not replace the marker.
     */
    public function marker(SqlError $syntax, \SqlParser\Parser\Node $tree, Session $session): SqlError
    {
        $cause = $syntax->getPrevious();
        if (!$cause instanceof \SqlParser\Parser\SyntaxException || ($tree->find('sf_tail') === [] && $tree->find('sp_tail') === [])) {
            return $syntax;
        }
        try {
            $statement = $session->semantics()->analyze($tree);
            $refusal = $this->refusal($statement->warnings());
            if ($refusal !== null) {
                return $this->markerNotes($refusal, $syntax, $cause->token->offset, $session);
            }
        } catch (\SqlSemantics\Construction\RejectedInput $refused) {
            if ($refused->problem instanceof RefusedProgramBody) {
                return $this->markerNotes($refused->problem, $syntax, $cause->token->offset, $session);
            }
        }
        return $syntax;
    }

    /**
     * Orders grammar warnings and typed header notes before a marker refusal.
     */
    public function markerNotes(RefusedProgramBody $refusal, SqlError $syntax, int $offset, Session $session): SqlError
    {
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $earlier = (new \MySqlMemory\Session\CacheOptions())->condition($syntax, $session->text, $session, $offset);
        if ($earlier !== null) {
            return $earlier;
        }
        $end = $this->boundary($refusal, $session, $offset);
        $syntax = $end < $offset ? $this->error($refusal, $session) : $syntax;
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $session->text, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        $tokens = $parser->tokenize($session->text);
        $warnings = new \MySqlMemory\Session\Parse\ModernWarnings();
        $modifiers = new \MySqlMemory\Session\Parse\Modifiers($tokens, $session->settings()->release());
        [$operators, $conflict] = $session->settings()->legacy() ? $modifiers->scan($end, $end) : [$warnings->before($tokens, $session->text, $end, $parser), null];
        if ($conflict !== null) {
            $syntax = (new \MySqlMemory\Session\CacheOptions())->refusal($conflict);
            $end = $modifiers->stoppedAt ?? $end;
            $notices = array_values(array_filter($notices, static fn (array $notice): bool => $notice[0] <= $end));
        }
        $notices = $warnings->merge($notices, $operators, $tokens);
        $notices = [...$notices, ...array_filter($this->notes($refusal, $session), static fn (array $note): bool => $note[0] <= $end)];
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        return $warnings->recorded(array_column($notices, 1), $syntax, $session);
    }

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
            if ($refusal->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration) {
                return (new Event\Alteration())->syntax($refusal->problem, $session);
            }
            if ($refusal->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch) {
                return \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
            }
            if (!$refusal->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($refusal->problem, $session);
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
        return new \SqlSemantics\Construction\RejectedInput($failure->problem, $end, $failure->aborts || $end < $failure->end);
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
