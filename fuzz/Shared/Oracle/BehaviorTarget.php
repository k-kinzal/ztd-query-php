<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

use Fuzz\Shared\Database\Connection;
use Fuzz\Shared\Database\Infrastructure;
use Fuzz\Shared\Database\Sandbox;
use Fuzz\Shared\Database\SessionExecutor;
use Fuzz\Shared\Input\Command;
use Fuzz\Shared\Input\Program;
use Fuzz\Shared\Input\Schema;
use PDOException;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\SessionFactory;

/**
 * Native execution is the oracle for all supported positive cases on all three platforms.
 */
final class BehaviorTarget
{
    /**
     * Use an independent reference database and a protected physical database.
     */
    public function __construct(private readonly SessionFactory $factory, private readonly Sandbox $native, private readonly Sandbox $physical)
    {
    }

    /**
     * Compare each operation and check physical isolation even after failure.
     * @throws Finding
     */
    public function __invoke(string $input): void
    {
        $sql = '<setup>';
        $snapshot = null;
        try {
            $this->native->reset();
            $this->physical->reset();
            $program = new Program($input);
            foreach ($program->schema->tables as $table) {
                $this->native->pdo->exec($program->schema->create($table));
                $this->physical->pdo->exec($program->schema->create($table));
                $this->physical->pdo->exec("INSERT INTO $table VALUES " . $program->schema->values(9000, 777, 'physical-only'));
            }
            $snapshot = $this->physical->catalog->snapshot();
            $connection = new Connection($this->physical->pdo);
            $config = new ZtdConfig(UnsupportedSqlBehavior::Exception, UnknownSchemaBehavior::Exception);
            $ztd = new SessionExecutor($this->factory->create($connection, $config), $connection);
            foreach ($program->schema->tables as $table) {
                for ($id = 1; $id <= $program->schema->rowCount; ++$id) {
                    $sql = "INSERT INTO $table VALUES " . $program->schema->values($id, $id - 2 + $program->schema->valueOffset, Schema::label($id + $program->schema->valueOffset + 8));
                    $this->step($ztd, new Command($sql, 'write'));
                }
            }
            State::verify($this->native, $ztd);
            foreach ($program->commands as $step => $command) {
                $sql = "Step $step: " . $command->sql;
                $this->step($ztd, $command);
                State::verify($this->native, $ztd);
                Comparison::same($snapshot, $this->physical->catalog->snapshot(), 'Physical database changed.');
            }
        } catch (PDOException|DatabaseException|Finding $failure) {
            Infrastructure::check($failure);
            throw new Finding('Input: ' . bin2hex($input) . "\n$sql\n" . $failure->getMessage(), 0, $failure);
        } finally {
            if ($snapshot !== null) {
                Comparison::same($snapshot, $this->physical->catalog->snapshot(), 'Physical database changed, including on a failed operation. Input: ' . bin2hex($input));
            }
        }
    }

    /**
     * Compare native and ZTD outcomes without permitting unexpected errors.
     * @throws Finding
     * @throws PDOException
     */
    public function step(SessionExecutor $ztd, Command $command): void
    {
        if ($this->native->driver === 'pgsql' && $command->feature === 'alter-add') {
            UnsupportedOperation::verify($this->native, $ztd, $command);
            return;
        }
        $before = $command->rejection === null ? null : $this->native->catalog->snapshot();
        $nativeFailure = $ztdFailure = null;
        $expected = $actual = null;
        try {
            $expected = $command->kind === 'read'
                ? $this->native->catalog->query($command->sql)
                : $this->native->pdo->exec($command->sql);
        } catch (PDOException $failure) {
            Infrastructure::check($failure);
            $nativeFailure = $failure;
        }
        try {
            $actual = $ztd->execute($command);
        } catch (PDOException|DatabaseException $failure) {
            Infrastructure::check($failure);
            $ztdFailure = $failure;
        }
        if ($command->rejection !== null) {
            Rejection::verify($nativeFailure, $command->rejection);
            Rejection::verify($ztdFailure, $command->rejection);
            Comparison::same($before, $this->native->catalog->snapshot(), 'Failed statement changed native state.');
            return;
        }
        if ($nativeFailure !== null || $ztdFailure !== null) {
            $failure = $nativeFailure ?? $ztdFailure;
            throw new Finding(($nativeFailure !== null ? 'Generated positive SQL failed natively: ' : 'ZTD rejected native-successful SQL: ') . $command->sql, 0, $failure);
        }
        if ($expected === false) {
            throw new Finding('Native statement silently failed: ' . $command->sql);
        }
        if ($command->kind === 'read') {
            if (!is_array($expected) || !is_array($actual)) {
                throw new Finding('A SELECT did not produce a result set.');
            }
            Comparison::same(Comparison::rows($expected), Comparison::rows($actual), 'SELECT result differs.');
        } elseif ($command->kind === 'write') {
            Comparison::same($expected, $actual, 'Affected row count differs.');
        }
    }

}
