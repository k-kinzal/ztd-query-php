<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Dml\Load\LoadTableGenerator;

/**
 * Executes generated DATA and XML imports against their generated declarations.
 */
#[CoversNothing]
#[Large]
final class LoadTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Absent and explicit empty destination lists select the declared columns.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedImportsWriteAllColumnsWithoutSql(GrammarRelease $release): void
    {
        $this->forAll(LoadTableGenerator::imports($release))->then(static function (array $parts) use ($release): void {
            [$create, $load, $expected, $contents] = $parts;
            self::assertIsString($contents);
            self::assertIsArray($expected);
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(LoadTable::class, $load);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), ['local_infile' => 1], [$database]))->connect(database: $database);
            $session->text = 'Generated file import without SQL';
            $session->running->readFile = static fn (string $file): string => $contents;
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            $reply = $execution->statement($load);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame(count($expected), $reply->affectedRows);
            self::assertSame(0, $reply->warnings);
            $table = $session->instance->dictionary->table($database, $create->name->name->value);
            self::assertNotNull($table);
            self::assertSame($expected, array_values($table->data->rows));
            self::assertSame('Generated file import without SQL', $session->text);
        });
    }

    /**
     * The same generated request reaches its operand condition only after its table exists.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesDependentConstraintsUsePublishedOperands(GrammarRelease $release): void
    {
        $this->forAll(LoadTableGenerator::constraints($release))->then(static function (array $input) use ($release): void {
            [$create, $missing, $declared, $code] = $input;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(LoadTable::class, $missing);
            self::assertInstanceOf(LoadTable::class, $declared);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated LOAD constraints without input SQL';
            $execution = new Execution($session);
            try {
                $execution->statement($missing);
                self::fail('The generated LOAD target does not exist yet.');
            } catch (\MySqlMemory\Error\SqlError $error) {
                self::assertSame(1146, $error->getCode());
            }
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            try {
                $execution->statement($declared);
                self::fail('The generated LOAD retains an operand constraint.');
            } catch (\MySqlMemory\Error\SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame('generated LOAD constraints without input SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
