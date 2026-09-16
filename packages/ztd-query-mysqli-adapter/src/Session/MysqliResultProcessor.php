<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Session;

use mysqli_result;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\ExecuteResult;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * Translates a native result into the session's simulated result.
 */
final class MysqliResultProcessor
{
    /**
     * Preserve the session result and translate its database exception for MySQLi callers.
     *
     * @throws ZtdMysqliException When the session cannot process the result.
     */
    public function process(Session $session, RewritePlan $plan, mysqli_result|false $result, int|string $affectedRows): ExecuteResult
    {
        if ($result === false) {
            return $session->createEmptyWriteResult();
        }
        try {
            /** @throws DatabaseException */
            return $session->processExecutedStatement($plan, new MysqliResultStatement($result, $affectedRows));
        } catch (DatabaseException $e) {
            throw new ZtdMysqliException($e->getMessage(), 0, $e);
        }
    }
}
