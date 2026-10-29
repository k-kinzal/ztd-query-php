<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use Tests\Eris\TestTrait;
use Tests\Generator\Semantics\Statement\ScriptGenerator;

/**
 * Executes composed declarations and workloads against independently generated
 * expectations. Every step requires either a specific result or a specific SQL
 * error. A refusal of a generated successful operation is a counterexample.
 */
#[CoversNothing]
#[Large]
final class ScriptExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A successful expression cannot pass this property by returning an SQL error.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesRecursiveExpressionsProduceTheirIndependentArithmetic(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\QueryRootGenerator::integerExpressions($release))->then(/** @param array{\SqlSemantics\Platform\MySql\Statement\Statement, list<list<string>>} $sample */ static function (array $sample) use ($release): void {
            [$query, $expected] = $sample;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated recursive expressions without SQL';
            $reply = (new Execution($session))->statement($query);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame($expected, $reply->rows);
            self::assertSame(0, $reply->warnings());
            self::assertSame('Generated recursive expressions without SQL', $session->text);
        });
    }

    /**
     * @param 'rows'|'set'|'query'|'update'|'delete'|'truncate'|'commit'|'rollback'|'view'|'index' $family
     */
    #[Test]
    #[DataProvider('providerTableChanges')]
    public function satisfiesDependentWorkloadsProduceTheirGeneratedResults(GrammarRelease $release, string $family): void
    {
        $this->forAll(ScriptGenerator::tableChanges($release, $family))->then(/** @param list<array{\SqlSemantics\Platform\MySql\Statement\Statement, list<list<string>>|int, int}> $sequence */ static function (array $sequence) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Composed semantic workload; no SQL input is available';
            $execution = new Execution($session);
            foreach ($sequence as $step) {
                self::assertIsArray($step);
                [$statement, $expected, $code] = $step;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
                try {
                    $reply = $execution->statement($statement);
                    self::assertSame(0, $code, 'A generated missing-state request must be refused.');
                    if (is_array($expected)) {
                        self::assertInstanceOf(ResultSet::class, $reply);
                        self::assertSame($expected, $reply->rows);
                        self::assertSame(-1, $session->variables->rowCount);
                        $output = $statement->statementInformation()->output;
                        self::assertNotNull($output);
                        self::assertCount(count($output->projection), $reply->columns);
                    } else {
                        self::assertInstanceOf(Completion::class, $reply);
                        self::assertSame($expected, $reply->affectedRows);
                        self::assertSame($expected, $session->variables->rowCount);
                    }
                    self::assertSame(0, $reply->warnings());
                } catch (SqlError $error) {
                    self::assertSame($code, $error->getCode(), $statement::class . ': ' . $error->getMessage());
                    self::assertSame('42S02', $error->sqlState());
                    $execution->failed($error);
                    self::assertSame(-1, $session->variables->rowCount);
                }
                self::assertSame([], $session->transaction->statements->running);
                self::assertSame([], $session->running->using);
                self::assertSame('Composed semantic workload; no SQL input is available', $session->text);
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->tables);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerTableChanges(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                foreach (['rows', 'set', 'query', 'update', 'delete', 'truncate', 'commit', 'rollback', 'view', 'index'] as $family) {
                    yield $release->value . ' ' . $family => [$release, $family];
                }
            }
        }
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
