<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use Closure;
use PDOStatement as NativePdoStatement;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\ExecuteResult;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * Coordinates rewritten preparation, native execution and shadow post-processing.
 * Its StatementInterface exposes native result rows to the core result-select runner.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class StatementExecution implements StatementInterface
{
    private ?ExecuteResult $result = null;

    /**
     * Retain the native statement and the plan that governs its execution.
     */
    public function __construct(
        private NativePdoStatement $statement,
        private readonly Session $session,
        private ?RewritePlan $plan,
        private readonly ?PreparedQuery $prepared = null,
        private readonly Bindings $bindings = new Bindings(),
    ) {
    }

    /**
     * Retain a parameter binding and apply it to the current native statement.
     *
     * @param Closure(NativePdoStatement): bool $binding
     */
    public function bindParameter(int|string $parameter, Closure $binding): bool
    {
        $this->bindings->parameter($parameter, $binding);
        return $binding($this->statement);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdPdoException When shadow post-processing fails.
     */
    public function execute(?array $params = null): bool
    {
        $this->result = null;
        if ($this->prepared !== null) {
            $this->plan = $this->prepared->rewrite();
            $compiled = $this->session->parameterBindingCompiler()?->compile($this->plan->sql(), $params)
                ?? ['sql' => $this->plan->sql(), 'params' => $params];
            $this->statement = $this->prepared->prepare($compiled['sql']);
            $params = $compiled['params'];
            $this->bindings->apply($this->statement);
        }
        if ($this->plan !== null && !$this->session->shouldExecute($this->plan)) {
            return false;
        }
        $success = $this->prepared === null
            ? $this->statement->execute($params)
            : (new ParameterBinder())->execute($this->statement, $params);
        if (!$success) {
            return false;
        }
        if ($this->plan !== null && $this->session->needsPostProcessing($this->plan)) {
            return $this->postProcess($this->plan);
        }
        return true;
    }

    /**
     * Apply a result-select query's rows to the session's shadow state.
     *
     * @throws ZtdPdoException When the core rejects the simulated mutation.
     */
    public function postProcess(RewritePlan $plan): bool
    {
        try {
            /** @throws DatabaseException */
            $this->result = $this->session->processExecutedStatement($plan, $this);
        } catch (DatabaseException $exception) {
            throw new ZtdPdoException($exception->getMessage(), 0, $exception);
        }
        return $this->result->isSuccess();
    }

    /**
     * Return the driver statement currently serving this execution.
     */
    public function native(): NativePdoStatement
    {
        return $this->statement;
    }

    /**
     * Return the result of shadow post-processing, when the query required it.
     */
    public function result(): ?ExecuteResult
    {
        return $this->result;
    }

    /**
     * Return the bindings retained across native statement replacements.
     */
    public function bindings(): Bindings
    {
        return $this->bindings;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(): array
    {
        return (new PdoStatement($this->statement))->fetchAll();
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        return (new PdoStatement($this->statement))->resultColumns($typeResolver);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
