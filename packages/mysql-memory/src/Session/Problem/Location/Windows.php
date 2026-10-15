<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Locator;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Places window diagnostics using their names and owning semantic expressions.
 *
 * @visibility MySqlMemory
 */
final class Windows
{
    /**
     * Adds the problems of window names no window of their block defines, each placed where the server checks the name.
     *
     * A window function or aggregate whose OVER names a window is checked where the call is
     * resolved; the window a specification refines, written after OVER in parentheses or in the
     * WINDOW clause, once the windows of the block are resolved, after ORDER BY. Unsupported
     * aggregate diagnostics identify their semantic call and follow the names of that block,
     * before resolution resumes in the enclosing expression (verified on a
     * live 8.4 server).
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>
     */
    public function located(Locator $locator, array $diagnostics, array $located): array
    {
        foreach ($locator->windows as [$name, $at]) {
            foreach ($diagnostics as $diagnostic) {
                if ($diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::UnknownWindow && $diagnostic->name === $name) {
                    $located[spl_object_id($diagnostic)] = [$diagnostic, ['field list', $at]];
                }
            }
        }

        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnsupportedWindowing && $diagnostic->expression !== null) {
                $at = $locator->windowChecks[spl_object_id($diagnostic->expression)] ?? null;
                if ($at !== null) {
                    $located[spl_object_id($diagnostic)] = [$diagnostic, ['field list', $at]];
                }
            }
        }

        return $located;
    }

}
