<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\SyntaxParsers;

/**
 * Resolves full-text parser dependencies completed before an input refusal.
 *
 * @visibility MySqlMemory
 */
final class ParserInput
{
    /**
     * Preserves a missing plugin error ahead of a later ALGORITHM or LOCK refusal.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        foreach ((new SyntaxParsers())->before($parser, $statement, $end, $semantics->profile()) as [$boundary, $dependency, $table]) {
            if ($session->settings()->legacy() && $session->variables->database === '' && $table?->schema === null) {
                return \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
            }
            if ($dependency->problem($session->resolution()) === null) {
                continue;
            }
            $notices = (new SyntaxWarnings())->completed($parser, $statement, $boundary, $semantics->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);

            return (new ModernWarnings())->recorded(array_column($notices, 1), AdministrationError::FunctionNotDefined->error($dependency->parser->value), $session);
        }

        return null;
    }
}
