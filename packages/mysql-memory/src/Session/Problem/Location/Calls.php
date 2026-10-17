<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Places a stored call's arity at its release-specific preparation boundary.
 *
 * Modern releases check the target and parameter count before arguments; legacy
 * releases bind the argument dependencies first. Missing targets already have
 * their ordinary reference diagnostic. This pass only adds known target counts.
 *
 * @visibility MySqlMemory
 */
final class Calls
{
    /**
     * Adds modern target-count conditions before the call's argument positions.
     *
     * @param array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>
     */
    public function located(Locator $locator, Session $session, array $located): array
    {
        if ($session->settings()->legacy()) {
            return $located;
        }
        foreach ($locator->nodes() as $node) {
            if (!$node instanceof FunctionCall || $node->native($session->settings()->release())) {
                continue;
            }
            $database = $node->schema->value ?? $session->variables->database;
            $schema = null;
            foreach ($session->instance->dictionary->schemas as $candidate) {
                $schema = $candidate->name === $database || ($schema === null && strcasecmp($candidate->name, $database) === 0) ? $candidate : $schema;
            }
            $routine = $schema?->functions[strtolower($node->name->value)] ?? null;
            $place = $locator->place($node);
            if ($routine !== null && $place !== null && $routine->parameterCount() !== count($node->arguments)) {
                $problem = ProgramError::RoutineArgumentCount->error('FUNCTION', $routine->schema . '.' . $routine->name, $routine->parameterCount(), count($node->arguments));
                $located[spl_object_id($problem)] = [$problem, $place];
            }
        }
        return $located;
    }
}
