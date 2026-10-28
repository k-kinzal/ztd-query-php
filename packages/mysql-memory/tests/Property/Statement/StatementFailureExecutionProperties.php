<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generator;
use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Tests\Generator\MySql\Statement\Account\Problem\InvalidUserAttributeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\UnknownGrantColumnGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\HistogramTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\UnknownHistogramColumnGenerator;

/**
 * Executes conditions whose result belongs to a command and its database state.
 * Histogram conditions produce result rows; DDL and account failures raise SQL errors.
 */
#[CoversNothing]
#[Large]
final class StatementFailureExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A refused heartbeat stops at its own option after the generated preceding notices.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedHeartbeatConditionsStopBeforeLaterOptions(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator::refusedHeartbeats($release))->then(static function (array $parts) use ($release): void {
            [$statement, $codes] = $parts;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource::class, $statement);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated replication options without SQL';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated heartbeat exceeds its maximum.');
            } catch (SqlError $error) {
                self::assertSame(1624, $error->getCode());
            }
            self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
            self::assertSame('Generated replication options without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * @param Generator<list<Statement>> $sequences Generated prerequisites and command
     */
    #[Test]
    #[DataProvider('providerCommands')]
    public function satisfiesGeneratedConditionsReachTheirOwningCommand(Generator $sequences, int $code): void
    {
        $this->forAll($sequences)->then(/** @param list<Statement> $statements */ static function (array $statements) use ($code): void {
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated command dependencies without source SQL';
            $execution = new Execution($session);
            $reply = null;
            $error = null;
            try {
                foreach ($statements as $statement) {
                    self::assertInstanceOf(Statement::class, $statement);
                    $reply = $execution->statement($statement);
                }
            } catch (SqlError $failure) {
                $error = $failure;
                $execution->failed($failure);
            }
            if ($code !== 0) {
                self::assertInstanceOf(SqlError::class, $error);
                self::assertSame($code, $error->getCode());
                self::assertSame(-1, $session->variables->rowCount);
            } else {
                self::assertNull($error);
                self::assertInstanceOf(ResultSet::class, $reply);
                $command = $statements[count($statements) - 1];
                self::assertInstanceOf(AnalyzeTable::class, $command);
                self::assertNotNull($command->histogram);
                $expected = count($command->tables) > 1
                    ? ['', 'histogram', 'Error', 'Only one table can be specified while modifying histogram statistics.']
                    : ['pbt.histogram_target', 'histogram', 'Error', "The column '" . $command->histogram->columns()[0]->value . "' does not exist."];
                self::assertSame([$expected], $reply->rows);
            }
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{Generator<list<Statement>>, int}>
     */
    public static function providerCommands(): iterable
    {
        yield 'Undeclared inspected relation kind' => [\Eris\Generators::vector(1, \Tests\Generator\MySql\Statement\Utility\Problem\TableOrViewGenerator::statements()), 1146];
        yield 'Undeclared stored function' => [\Eris\Generators::vector(1, \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredRoutineGenerator::queries()), 1305];
        yield 'Duplicate ALTER column' => [DuplicateColumnGenerator::statements(), 1060];
        yield 'Missing GRANT column' => [UnknownGrantColumnGenerator::statements(), 1054];
        yield 'Invalid user attribute' => [InvalidUserAttributeGenerator::statements(), 3982];
        yield 'Several histogram tables' => [HistogramTablesGenerator::statements(), 0];
        yield 'Missing histogram column' => [UnknownHistogramColumnGenerator::statements(), 0];
    }
}
