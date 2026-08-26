<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOStatement;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * Prepares one statement again for every set of parameters it is run with.
 *
 * What ZTD rewrites a statement into depends on the values bound to it: a
 * shadow is built from the rows the parameters name, and a statement prepared
 * once cannot carry a shadow built later. So the statement is rewritten and
 * prepared afresh on each execute(), and this is what remembers enough about
 * the original to do that.
 */
final class PdoPreparedExecution
{
    /**
     * Driver options the statement is prepared with.
     *
     * @var array<int, bool|int|string>
     */
    private readonly array $options;

    /**
     * Binds the execution to the statement it will keep preparing.
     *
     * @param PDO $pdo Connection the rewritten statement is prepared on
     * @param Session $session Session that rewrites the statement
     * @param string $sql Statement as it was written
     * @param array<mixed> $options Driver options, as PDO::prepare() takes them
     * @param PdoParameterBinder $parameterBinder Binds the caller's parameters to the rewritten statement
     *
     * @throws ZtdPdoException When an option is one PDO cannot be given
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Session $session,
        private readonly string $sql,
        array $options = [],
        private readonly PdoParameterBinder $parameterBinder = new PdoParameterBinder(),
    ) {
        $driverOptions = [];
        foreach ($options as $attribute => $value) {
            if (!is_int($attribute) || !(is_bool($value) || is_int($value) || is_string($value))) {
                throw new ZtdPdoException(sprintf(
                    'Driver option %s must be a PDO attribute set to a bool, int or string, %s given.',
                    is_int($attribute) ? sprintf('#%d', $attribute) : sprintf('"%s"', $attribute),
                    get_debug_type($value),
                ));
            }
            $driverOptions[$attribute] = $value;
        }
        $this->options = $driverOptions;
    }

    /**
     * Rewrites the statement for these parameters and prepares it.
     *
     * @param array<int|string, mixed>|null $params Parameters the statement is about to be run with, or null for those already bound
     *
     * @return array{statement: PDOStatement, plan: RewritePlan, params: array<int|string, mixed>|null} The prepared statement, what ZTD will carry out, and the parameters as the rewrite left them
     *
     * @throws ZtdPdoException When the driver will not prepare the rewritten statement
     */
    public function prepare(?array $params): array
    {
        $plan = $this->session->rewrite($this->sql);
        $compiled = $this->session->parameterBindingCompiler()?->compile($plan->sql(), $params)
            ?? ['sql' => $plan->sql(), 'params' => $params];
        $statement = $this->pdo->prepare($compiled['sql'], $this->options);
        if ($statement === false) {
            throw new ZtdPdoException('PDO failed to prepare rewritten SQL.');
        }

        return ['statement' => $statement, 'plan' => $plan, 'params' => $compiled['params']];
    }

    /**
     * Answers what binds the caller's parameters to the rewritten statement.
     *
     * @return PdoParameterBinder The binder this execution was built with
     */
    public function parameterBinder(): PdoParameterBinder
    {
        return $this->parameterBinder;
    }
}
