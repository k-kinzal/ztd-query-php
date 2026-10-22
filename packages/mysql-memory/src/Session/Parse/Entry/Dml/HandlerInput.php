<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Dml;

use LogicException;
use MySqlMemory\Command\Access\HandlerCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Program\Expression\RowUse;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerFacts;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares a refused HANDLER read from its complete semantic dependencies.
 *
 * Opening the handler precedes WHERE; WHERE precedes index lookup and key
 * operands. No rejected operand is replaced with a fabricated scalar.
 *
 * @visibility MySqlMemory
 */
final class HandlerInput
{
    /**
     * Returns the first native preparation condition of the retained request.
     */
    public function error(RefusedRead $read, Session $session): SqlError
    {
        try {
            $this->resolve($read, $session);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves handler state and operand scopes before the intrinsic refusal.
     *
     * @throws SqlError Always, after earlier dependencies have been prepared
     * @throws LogicException When a refused structure has lost its non-scalar operand
     */
    public function resolve(RefusedRead $read, Session $session): never
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        (new HandlerFacts())->read($read->handler, $read->values, $read->where, $read->limit, $derivation);
        $facts = $derivation->facts();
        $this->early($read, $facts, $session);
        if ($session->preparation->inputPrepared) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        $command = new HandlerCommand();
        $handler = $command->handler($session, $read->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        assert($table !== null);
        $scope = new Scope();
        $scope->place($read->handler, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        if ($read->where !== null) {
            $this->prepare($read->where, $facts, $scope, $session);
        }
        if ($read->index !== null) {
            $key = $command->key($table, $read->index->value, $handler);
            if (count($read->values) > count($key->columns)) {
                throw \MySqlMemory\Error\Family\SchemaError::TooManyKeyParts->error(count($key->columns));
            }
        }
        foreach ($read->values as $value) {
            if (!$value instanceof DefaultRequest) {
                $this->prepare($value, $facts, new Scope(), $session);
            }
        }
        throw new LogicException('A refused handler read must retain a non-scalar operand.');
    }

    /**
     * Preserves parsing conditions, stored-call database lookup and LIMIT order.
     *
     * @throws SqlError When an input condition precedes opening the handler
     */
    public function early(RefusedRead $read, Facts $facts, Session $session): void
    {
        $operands = [...$read->values, ...($read->where === null ? [] : [$read->where])];
        foreach ($operands as $operand) {
            (new \MySqlMemory\Session\Problem\Identifiers())->check($operand, $session->settings()->release());
        }
        foreach ($facts->diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic)) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        if ($session->variables->database === '') {
            foreach ($operands as $operand) {
                foreach ([...(new Walker())->find($operand, FunctionCall::class), ...(new Walker())->find($operand, DeferredCall::class)] as $call) {
                    if ($call->schema === null && !$call->native($session->settings()->release())) {
                        throw (new Errors())->routine($call, $session);
                    }
                }
            }
        }
        (new Reading())->limited($read->limit, $session);
        foreach ($facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof NotSupportedYet && $diagnostic->feature === 'stored functions in HANDLER ... READ') {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
    }

    /**
     * Prepares a predicate or key without evaluating or reinterpreting its names.
     *
     * @throws SqlError When a dependency or the operand's scalar role fails
     */
    public function prepare(Scalar|RowOperand|DeferredOperand $operand, Facts $facts, Scope $scope, Session $session): void
    {
        $evaluation = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new Planner($operand, $facts, $session->settings(), $connection, $session->instance->dictionary);
        if ($operand instanceof Scalar) {
            $planner->compiler->compile($operand, $scope);
            return;
        }
        (new RowUse())->prepare($operand, $planner->compiler, $scope);
        throw (new RowUse())->error($operand);
    }
}
