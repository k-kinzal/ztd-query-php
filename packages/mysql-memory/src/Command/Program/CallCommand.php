<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Declared;
use MySqlMemory\Typing\Domain;
use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Operation;

/**
 * Executes CALL of a stored procedure whose body is a statement, or a block of statements without declarations or flow control.
 *
 * A missing procedure is ER_SP_DOES_NOT_EXIST, and another number of arguments than of
 * parameters ER_SP_WRONG_NO_OF_ARGS. Each argument is stored into its parameter as into a
 * column of its type. The statements of the body run in turn in the database of the procedure,
 * each parameter read as the value bound to it; a query answers a result set, and the
 * procedure answers its result sets and then the completion of its last statement. A body with
 * declarations, flow control, or OUT and INOUT parameters is not executed yet.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/call.html.
 *
 * @visibility MySqlMemory
 */
final class CallCommand implements Command
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
     * Runs the procedure.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ProcedureCall);
        $name = $statement->procedure->name->value;
        $database = ProgramSource::database($statement->procedure->schema, $session);
        $routine = $session->instance->dictionary->schema($database)->procedures[strtolower($name)] ?? null;
        if ($routine === null) {
            throw ProgramError::RoutineMissing->error('PROCEDURE', $database . '.' . $name);
        }
        $parameters = $routine->statement->parameters->parameters;
        if (count($parameters) !== count($statement->arguments)) {
            throw ProgramError::RoutineArgumentCount->error('PROCEDURE', $database . '.' . $name, count($parameters), count($statement->arguments));
        }
        $bound = $this->arguments($statement, $routine, $operation, $session, $context, $connection);
        $statements = $this->statements($routine, $session);
        $current = $session->variables->database;
        $session->variables->database = $routine->schema;
        $replies = [];
        try {
            foreach ($statements as [$text, $positions]) {
                $replies[] = $session->execute($text, array_map(static fn (int $position): array => $bound[$position], $positions), true);
            }
        } finally {
            $session->variables->database = $current;
        }
        $results = array_values(array_filter($replies, static fn (Reply $reply): bool => $reply instanceof ResultSet));
        $last = end($replies);
        $completion = $last instanceof Completion ? $last : new Completion(0, 0, $session->diagnostics->count());

        return $results === [] ? $completion : new Batch([...$results, new Completion(0, 0, $session->diagnostics->count())]);
    }

    /**
     * Answers the value and type each argument binds to its parameter, stored as into a column of the type of the parameter.
     *
     * @return list<array{int|float|string|null, Domain}>
     *
     * @throws SqlError When an argument is refused, or a parameter is OUT or INOUT
     */
    public function arguments(ProcedureCall $statement, Routine $routine, Operation $operation, Session $session, Context $context, Connection $connection): array
    {
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $declared = new Declared(Collation::named($routine->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci'));
        $context->strict = $context->modes->strict();
        $bound = [];
        foreach ($routine->statement->parameters->parameters as $index => $parameter) {
            if ($parameter->mode === ParameterMode::Out || $parameter->mode === ParameterMode::InOut) {
                throw StatementError::NotSupportedYet->error('OUT and INOUT parameters of a procedure');
            }
            $collation = $parameter->collation?->name === null ? null : Collation::named($parameter->collation->name->value);
            $domain = $declared->domain($parameter->type, $collation)->withNullable(true);
            $domain = $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String ? new Domain($domain->kind, $domain->field, $domain->length, 0, $domain->unsigned, $domain->collation, true, $domain->members, $domain->coercibility) : $domain;
            $argument = $planner->compiler->compile($statement->arguments[$index], new Scope());
            $value = (new Store($context))->value($argument->evaluate(new Frame($context)), $argument->domain(), new ColumnDefinition($parameter->name->value, $domain, Fill::none()));
            $bound[] = [$value, $domain];
        }
        $context->strict = false;

        return $bound;
    }

    /**
     * Answers the statements of the body of a procedure, each with its parameters written as markers and the position of the parameter each marker binds.
     *
     * A select item that reads a parameter keeps the name it is written with.
     *
     * @return list<array{string, list<int>}>
     *
     * @throws SqlError When the body declares variables or has flow control
     */
    public function statements(Routine $routine, Session $session): array
    {
        $text = 'CREATE PROCEDURE p(' . $routine->parameters . ') ' . $routine->body;
        $tree = $session->semantics()->parser()->parse($text);
        $members = $tree->find('sp_proc_stmt');
        $body = $members[0] ?? null;
        $block = $body?->find('sp_block_content')[0] ?? null;
        $declarations = $block?->find('sp_decls')[0] ?? null;
        $statements = $body !== null && $body->ordinal === 0 ? [$body] : array_slice($members, 1);
        if ($body === null || ($body->ordinal !== 0 && ($block === null || ($declarations !== null && !$declarations->isEmpty()) || $tree->find('sp_labeled_block') !== []))) {
            throw StatementError::NotSupportedYet->error('CALL of a procedure with declarations or flow control');
        }
        $names = array_map(static fn ($parameter): string => strtolower($parameter->name->value), $routine->statement->parameters->parameters);
        $written = [];
        foreach ($statements as $member) {
            if ($member->ordinal !== 0) {
                throw StatementError::NotSupportedYet->error('CALL of a procedure with declarations or flow control');
            }
            $written[] = $this->statement($member, $text, $names);
        }

        return $written;
    }

    /**
     * Writes one statement of a body with its parameters as markers.
     *
     * @param list<string> $names The lower-case names of the parameters
     * @return array{string, list<int>}
     */
    public function statement(Node $member, string $text, array $names): array
    {
        $span = $member->span() ?? [0, 0];
        $edits = [];
        foreach ([...$member->find('simple_ident'), ...$member->find('limit_option')] as $use) {
            $tokens = array_values(array_filter($use->tokens(), static fn (Token $token): bool => $token->text !== '.'));
            $position = count($tokens) === 1 ? array_search(strtolower(\MySqlMemory\Command\View\ViewWrites::unquoted($tokens[0]->text)), $names, true) : false;
            $at = $use->span();
            if (is_int($position) && $at !== null) {
                $edits[$at[0]] = [$at[0], $at[1], '?', $position];
            }
        }
        foreach ($member->find('select_item') as $item) {
            $at = $item->span();
            $alias = $item->find('select_alias')[0] ?? null;
            $read = $at !== null && array_filter($edits, static fn (array $edit): bool => $edit[0] >= $at[0] && $edit[1] <= $at[1]) !== [];
            if ($item->ordinal === 1 && $read && ($alias === null || $alias->isEmpty())) {
                $edits[$at[1]] = [$at[1], $at[1], ' AS ' . Routine::quoted(substr($text, $at[0], $at[1] - $at[0])), null];
            }
        }
        ksort($edits);
        $positions = array_values(array_filter(array_column($edits, 3), static fn (?int $position): bool => $position !== null));
        krsort($edits);
        $statement = substr($text, $span[0], $span[1] - $span[0]);
        foreach ($edits as [$start, $end, $replacement]) {
            $statement = substr($statement, 0, $start - $span[0]) . $replacement . substr($statement, $end - $span[0]);
        }

        return [rtrim($statement, " \t\n\r;"), $positions];
    }
}
