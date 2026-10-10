<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use Closure;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Assigner;
use MySqlMemory\Variable\Scope as VariableScope;
use Override;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope as Written;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes SET: assigns user variables, system variables and the connection character set.
 *
 * Every value is computed before any variable is assigned; an assignment that is refused leaves
 * every variable as it was. In a stored program a variable of the program is assigned as soon
 * as its value is computed, so that the items after it read the new value; a bare name assigned
 * to it is a variable of the program (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility MySqlMemory
 */
final class SetCommand implements Command
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
     * Assigns the variables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof SetVariables);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $frame = new Frame($context);
        $assigner = new Assigner($session->variables, $context);
        $actions = [];
        foreach ($statement->items as $position => $item) {
            $actions[] = $this->action($item, $planner, $frame, $assigner, $session, $statement->scopeOf($position));
        }
        $user = $session->variables->user;
        $values = $session->variables->session;
        $globals = $session->variables->globals->values;
        $caches = $session->variables->globals->caches;
        try {
            foreach ($actions as $action) {
                $action();
            }
        } catch (\MySqlMemory\Error\SqlError $error) {
            $session->variables->user = $user;
            $session->variables->session = $values;
            $session->variables->globals->values = $globals;
            $session->variables->globals->caches = $caches;
            throw $error;
        }
        $session->instance->registry->eventScheduler->configured($session->variables->globals, $session->variables->catalog, $session->instance->dictionary);

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Computes the value of one assignment and answers the action that assigns it.
     *
     * @param Written|null $inherited The scope of the assignment: the one it writes, or the last one an earlier assignment wrote (verified on a live 8.4 server)
     */
    public function action(object $item, Planner $planner, Frame $frame, Assigner $assigner, Session $session, ?Written $inherited = null): Closure
    {
        if ($item instanceof UserAssignment) {
            $value = $planner->compiler->compile($item->value, new Scope());
            $result = $value->evaluate($frame);
            $stored = $planner->compiler->names->stored($value->domain());

            return static fn () => $session->variables->assign($item->variable->name->value, $result, $stored);
        }
        if ($item instanceof SystemAssignment) {
            return $this->system($item, $planner, $frame, $assigner, $session);
        }
        $variable = $item instanceof NameAssignment && $inherited === null ? $this->variable($item, $session) : null;
        if ($variable !== null) {
            $this->local($variable, $item, $planner, $frame, $session);

            return static fn () => null;
        }
        if ($item instanceof NameAssignment) {
            [$value, $domain] = $this->value($item->value, $planner, $frame);
            $scope = $this->scope($inherited);
            $name = $item->name->value;
            $cache = $item->qualifier?->value;

            return static function () use ($assigner, $name, $scope, $value, $domain, $cache): void {
                $assigner->retired($name);
                $assigner->assign($name, $scope, $value, $domain, $cache);
            };
        }
        if ($item instanceof SetNames || $item instanceof SetCharacterSet) {
            return $this->charset($item, $planner, $assigner, $session);
        }

        throw StatementError::NotSupportedYet->error('SET ' . (new ReflectionClass($item))->getShortName());
    }

    /**
     * Computes the value of an assignment to a variable named with `@@` and answers the action that assigns it; one to the transaction characteristics without a scope sets those of the next transaction.
     */
    public function system(SystemAssignment $item, Planner $planner, Frame $frame, Assigner $assigner, Session $session): Closure
    {
        [$value, $domain] = $this->value($item->value, $planner, $frame);
        $scope = $this->scope($item->variable->scope);
        $definition = $session->variables->catalog->find($item->variable->name->value);
        $name = $item->variable->name->value;
        if ($item->variable->scope === null && $domain !== null && $definition !== null && in_array($definition->name, ['transaction_isolation', 'tx_isolation', 'transaction_read_only', 'tx_read_only'], true)) {
            return static function () use ($session, $definition, $assigner, $value, $domain, $name): void {
                $assigner->retired($name);
                (new Transaction\SetTransactionCommand())->next($session, $definition->name, (string) $assigner->check($definition, $value, $domain));
            };
        }
        $cache = $item->variable->instance?->value;

        return static function () use ($assigner, $name, $scope, $value, $domain, $cache): void {
            $assigner->retired($name);
            $assigner->assign($name, $scope, $value, $domain, $cache);
        };
    }

    /**
     * Answers the action of SET NAMES or SET CHARACTER SET: the character set of the client and the results, and for SET NAMES the character set and collation of the connection; DEFAULT names character_set_server.
     */
    public function charset(SetNames|SetCharacterSet $item, Planner $planner, Assigner $assigner, Session $session): Closure
    {
        $server = $session->instance->catalog->find('character_set_server');
        $default = $server === null ? 'utf8mb4' : (string) $session->instance->globals->value($server);
        $charset = $item->charset->name->value ?? $default;
        $collation = $item instanceof SetNames && $item->collation?->name !== null ? $item->collation->name->value : (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($charset)?->defaultCollation($planner->settings->release())->name ?? $charset);

        return static function () use ($assigner, $charset, $collation, $item): void {
            $text = Domain::string(64, Collation::known('utf8mb4_0900_ai_ci'));
            foreach (['character_set_client', 'character_set_results'] as $name) {
                $assigner->assign($name, VariableScope::Session, $charset, $text);
            }
            if ($item instanceof SetNames) {
                $assigner->assign('collation_connection', VariableScope::Session, $collation, $text);
                $assigner->assign('character_set_connection', VariableScope::Session, $charset, $text);
            }
        };
    }

    /**
     * Assigns a variable of the running stored program at once: the value of the expression, or of the variable a bare name names, stored as into a column of its type under the strictness of sql_mode.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is refused, or a bare name names no variable
     */
    public function local(\MySqlMemory\Program\Variable $variable, NameAssignment $item, Planner $planner, Frame $frame, Session $session): void
    {
        $strict = new Context($frame->context->modes, $frame->context->diagnostics, $frame->context->variables, $frame->context->started, $frame->context->modes->strict());
        if ($item->value instanceof BareName) {
            $read = $session->program?->variable($item->value->word->value) ?? throw \MySqlMemory\Error\Family\QueryError::BadField->error($item->value->word->value, 'field list');
            $variable->assign($read->value, $read->domain, $strict);

            return;
        }
        if ($item->value instanceof Scalar) {
            $compiled = $planner->compiler->compile($item->value, new Scope());
            $variable->assign($compiled->evaluate(new Frame($strict)), $compiled->domain(), $strict);
        }
    }

    /**
     * Finds the variable of the running stored program an assignment without a scope names: a parameter or local variable, or a column of the NEW row of a trigger; null when it names a system variable.
     *
     * The value of such a variable is computed and stored as a value written to a column of its
     * type, so that a warning is an error under a strict sql_mode (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
     *
     * @throws \MySqlMemory\Error\SqlError When the assignment names the OLD row, or the NEW row of an AFTER trigger
     */
    public function variable(NameAssignment $item, Session $session): ?\MySqlMemory\Program\Variable
    {
        $program = $session->program;
        if ($program === null || $item->qualifier === null) {
            return $program?->variable($item->name->value);
        }
        $row = $program->row($item->qualifier->value);
        if ($row === null) {
            return null;
        }
        if (!$row->writable) {
            throw \MySqlMemory\Error\Family\ProgramError::TriggerRowChange->error(strtoupper($item->qualifier->value), strcasecmp($item->qualifier->value, 'NEW') === 0 ? 'after ' : '');
        }

        return $row->variable($item->name->value) ?? throw \MySqlMemory\Error\Family\QueryError::BadField->error($item->name->value, strtoupper($item->qualifier->value));
    }

    /**
     * Computes an assigned value: an expression, a bare word, or DEFAULT (null domain).
     *
     * @return array{int|float|string|null, Domain|null}
     */
    public function value(Scalar|SetWord $value, Planner $planner, Frame $frame): array
    {
        if ($value instanceof SetWord) {
            return $value === SetWord::Default ? [null, null] : [$value->value, Domain::string(16, Collation::known('utf8mb4_0900_ai_ci'))];
        }
        if ($value instanceof BareName) {
            return [$value->word->value, Domain::string(64, Collation::known('utf8mb4_0900_ai_ci'))];
        }
        $compiled = $planner->compiler->compile($value, new Scope());

        return [$compiled->evaluate($frame), $compiled->domain()];
    }

    /**
     * Answers the scope an assignment writes.
     */
    public function scope(?Written $scope): VariableScope
    {
        return $scope === Written::Global || $scope === Written::Persist || $scope === Written::PersistOnly ? VariableScope::Global : VariableScope::Session;
    }
}
