<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOStatement;
use RuntimeException;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * The pdo prepared execution.
 */
final class PdoPreparedExecution
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Session $session,
        private readonly string $sql,
        private readonly array $options,
        private readonly PdoParameterBinder $parameterBinder = new PdoParameterBinder(),
    ) {
    }

    /**
     * @param array<int|string, mixed>|null $params
     * @return array{statement: PDOStatement, plan: RewritePlan, params: array<int|string, mixed>|null}
     *
     * @throws RuntimeException
     */
    public function prepare(?array $params): array
    {
        $plan = $this->session->rewrite($this->sql);
        $compiled = $this->session->parameterBindingCompiler()?->compile($plan->sql(), $params)
            ?? ['sql' => $plan->sql(), 'params' => $params];
        $statement = $this->pdo->prepare($compiled['sql'], $this->options);
        if ($statement === false) {
            throw new RuntimeException('PDO failed to prepare rewritten SQL.');
        }

        return ['statement' => $statement, 'plan' => $plan, 'params' => $compiled['params']];
    }

    /**
     * Parameter binder.
     *
     * @return PdoParameterBinder
     */
    public function parameterBinder(): PdoParameterBinder
    {
        return $this->parameterBinder;
    }
}
