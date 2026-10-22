<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\System\Performance\StatusVariables;
use MySqlMemory\Variable\Scope;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW VARIABLES and SHOW STATUS: the system or status variables of a scope, in name order.
 *
 * SHOW VARIABLES lists the session value of each system variable, or with GLOBAL the global
 * value of each variable that has one; a NULL value is listed as the empty string. The session
 * timestamp is the time of the statement, and pseudo_thread_id the connection id. The rows are
 * read from the variables tables of the Performance Schema, which the column metadata names.
 * LIKE matches the names without regard to case. SHOW STATUS lists the status variables of the
 * release, and the statement counters the tables leave out ({@see StatusVariables}).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-status.html.
 *
 * @visibility MySqlMemory
 */
final class ShowVariablesCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the variables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowVariables || $statement instanceof ShowStatus);
        $global = $statement->scope === VariableScope::Global;
        $oldest = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 || ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 && in_array($session->variables->read('show_compatibility_56'), ['ON', 1], true));
        $rows = $statement instanceof ShowVariables ? $this->variables($session, $global, $context, $oldest) : $this->status($session, $global, $context->started, $context->zone());
        $headings = $this->headings($global, $oldest, $session->settings()->legacy(), $statement instanceof ShowVariables);

        return (new Listing($headings))->result($rows, $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb4_0900_ai_ci');
    }

    /**
     * @return list<Heading> The variable output domains for the selected catalog
     */
    public function headings(bool $global, bool $oldest, bool $legacy, bool $variables): array
    {
        $table = ($global ? 'global_' : 'session_') . ($variables ? 'variables' : 'status');
        if ($oldest) {
            $table = $variables ? 'VARIABLES' : 'STATUS';
        }
        $schema = $oldest ? 'information_schema' : ($legacy ? '' : 'performance_schema');
        return [
            Heading::text('Variable_name', Field::VarString, 64, ColumnFlag::NotNull->value | ($oldest ? 0 : ColumnFlag::NoDefaultValue->value), 0, $oldest ? 'VARIABLE_NAME' : 'Variable_name', $table, $table, $schema, 'utf8mb4_0900_ai_ci'),
            Heading::text('Value', Field::VarString, 1024, 0, 0, $oldest ? 'VARIABLE_VALUE' : 'Value', $table, $table, $schema, 'utf8mb4_0900_ai_ci'),
        ];
    }

    /**
     * Answers the name and value of each status variable of a scope, as performance_schema lists them and with the statement counters.
     *
     * @return list<array{string, string}>
     */
    public function status(Session $session, bool $global, ?float $instant = null, ?\MySqlMemory\Value\Zone $zone = null): array
    {
        return StatusVariables::of($session->settings()->release())->values($session->instance, $global, false, $this->threads($session), false, $session->id, $instant ?? $session->variables->instant(), $zone);
    }

    /**
     * Counts connected clients, optionally including the event scheduler as COM_STATISTICS does.
     */
    public function threads(Session $session, bool $daemon = false): int
    {
        $connected = 0;
        foreach ($session->instance->sessions as $id => $reference) {
            $connected += $reference->get() !== null && isset($session->instance->registry->threads->connected[$id]) ? 1 : 0;
        }

        return $connected + ($daemon && $session->instance->registry->eventScheduler->row() !== null ? 1 : 0);
    }

    /**
     * Answers the name and value of each system variable of a scope, in name order.
     *
     * @return list<list<string>>
     */
    public function variables(Session $session, bool $global, Context $context, bool $compatibility = false): array
    {
        $variables = $session->variables;
        $definitions = $variables->catalog->definitions;
        ksort($definitions, SORT_STRING);
        $rows = [];
        foreach ($definitions as $name => $definition) {
            if (($global && $definition->reach === Reach::Session) || (!$global && !$compatibility && $name === 'gtid_executed' && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744)) {
                continue;
            }
            $value = match (true) {
                !$global && $name === 'timestamp' => sprintf('%.6f', $context->started),
                default => $variables->system($definition, $global ? Scope::Global : Scope::Session),
            };
            $rows[] = [$definition->name, is_int($value) && $definition->shape === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape::Unsigned ? \MySqlMemory\Value\Integer::text($value, true) : (string) $value];
        }

        return $rows;
    }
}
