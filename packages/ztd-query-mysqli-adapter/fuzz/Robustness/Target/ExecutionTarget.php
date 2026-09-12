<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

/**
 * Replays grammar plans through a fresh adapter and verifies physical isolation.
 */
final class ExecutionTarget
{
    /**
     * Bind the immutable grammar and the disposable native fixture connection.
     */
    public function __construct(private MySqlProvider $provider, private mysqli $native)
    {
    }

    /**
     * Run one reproducible grammar case with no state shared by adapter instances.
     *
     * @throws Error When execution leaks an unexpected error or changes physical rows.
     */
    public function __invoke(string $input): void
    {
        $constraint = GenerationPlan::fromRule('simple_statement_or_begin')->requiringNonEmpty();
        $plan = (new BytePlanCompiler())->compile($input, $this->provider->planner(), $constraint);
        $sql = $this->provider->generate($plan);
        $ztd = ZtdMysqli::fromMysqli($this->native);
        try {
            $result = $ztd->query($sql);
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        } catch (ZtdMysqliException) {
            // Unsupported grammar and unknown schema are explicit adapter rejections.
        } catch (mysqli_sql_exception $exception) {
            // Grammar identifiers need not exist in the fixed schema.
            if (!in_array($exception->getCode(), [1054, 1146, 1109, 1327], true)) {
                throw new Error("Unexpected mysqli error\nPHP: " . PHP_VERSION . "\nMySQL: " . $this->native->server_info . "\nInput: " . bin2hex($input) . "\nSQL: $sql", 0, $exception);
            }
        }
        try {
            $physical = $this->native->query('SELECT id, name FROM users ORDER BY id');
            if (!$physical instanceof mysqli_result || $physical->fetch_all(MYSQLI_ASSOC) !== [['id' => '1', 'name' => 'Alice'], ['id' => '2', 'name' => 'Bob']]) {
                throw new Error("Physical table changed\nInput: " . bin2hex($input) . "\nSQL: $sql");
            }
            $physical->free();
        } finally {
            $this->native->rollback();
            $this->native->autocommit(true);
        }
    }
}
