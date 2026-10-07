<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use Closure;
use MySqlMemory\Error\ErrorCode;
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
 * every variable as it was.
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
        foreach ($statement->items as $item) {
            $actions[] = $this->action($item, $planner, $frame, $assigner, $session);
        }
        $user = $session->variables->user;
        $values = $session->variables->session;
        $globals = $session->variables->globals->values;
        try {
            foreach ($actions as $action) {
                $action();
            }
        } catch (\MySqlMemory\Error\SqlError $error) {
            $session->variables->user = $user;
            $session->variables->session = $values;
            $session->variables->globals->values = $globals;
            throw $error;
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Computes the value of one assignment and answers the action that assigns it.
     */
    public function action(object $item, Planner $planner, Frame $frame, Assigner $assigner, Session $session): Closure
    {
        if ($item instanceof UserAssignment) {
            $value = $planner->compiler->compile($item->value, new Scope());
            $result = $value->evaluate($frame);
            $stored = $planner->compiler->names->stored($value->domain());

            return static fn () => $session->variables->assign($item->variable->name->value, $result, $stored);
        }
        if ($item instanceof SystemAssignment) {
            [$value, $domain] = $this->value($item->value, $planner, $frame);
            $scope = $this->scope($item->variable->scope);

            return static fn () => $assigner->assign($item->variable->name->value, $scope, $value, $domain);
        }
        if ($item instanceof NameAssignment) {
            [$value, $domain] = $this->value($item->value, $planner, $frame);
            $scope = $this->scope($item->scope);

            return static fn () => $assigner->assign($item->name->value, $scope, $value, $domain);
        }
        if ($item instanceof SetNames || $item instanceof SetCharacterSet) {
            $charset = $item->charset->name?->value ?? 'utf8mb4';
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

        throw ErrorCode::NotSupportedYet->error('SET ' . (new ReflectionClass($item))->getShortName());
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
