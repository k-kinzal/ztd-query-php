<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Eris\Quantifier\ForAll;
use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use Iterator;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Tests\Eris\ShrinkerFactory;
use Tests\Generator\Semantics\Statement\ScriptGenerator;

#[CoversNothing]
#[Large]
final class WorkloadShrinkingTest extends TestCase
{
    public function testMappedCandidatesArePublishedOnlyWhenVisited(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(20261029);
        $publications = 0;
        $generator = \Eris\Generators::map(static function (array $values) use (&$publications): array {
            $publications++;
            return $values;
        }, \Tests\Generator\Operands::vector(32, \Eris\Generators::choose(1, 100)));
        $original = $generator(100, $random);
        self::assertSame(1, $publications);
        $candidates = $generator->shrink($original);
        self::assertSame(1, $publications);
        $iterator = $candidates->getIterator();
        self::assertInstanceOf(Iterator::class, $iterator);
        $iterator->rewind();
        self::assertSame(2, $publications);
        $iterator->next();
        self::assertSame(3, $publications);
    }

    public function testSequenceShrinkingCanRemoveEveryPositionWithoutReplacingTheOthers(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(20261029);
        $generator = \Tests\Generator\Operands::seq(\Eris\Generators::choose(1, 100));
        $original = $generator(32, $random);
        $values = $original->unbox();
        self::assertGreaterThan(1, count($values));
        $removals = array_slice(iterator_to_array($generator->shrink($original)), 0, count($values));
        self::assertSame(array_map(static fn (int $index): array => [...array_slice($values, 0, $index), ...array_slice($values, $index + 1)], array_keys($values)), array_map(static fn ($candidate): array => $candidate->unbox(), $removals));
    }

    public function testWideVectorsShrinkOneComposedElementAtATime(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(20261029);
        $generator = \Tests\Generator\Operands::vector(32, \Tests\Generator\Operands::tuple(\Eris\Generators::choose(1, 100), \Eris\Generators::choose(1, 100)));
        $original = $generator(100, $random);
        $candidates = iterator_to_array($generator->shrink($original));
        $count = count($candidates);
        self::assertSame(array_fill(0, $count, 32), array_map(static fn ($candidate): int => count($candidate->unbox()), $candidates));
        self::assertSame(array_fill(0, $count, 1), array_map(static fn ($candidate): int => count(array_filter(array_keys($original->unbox()), static fn (int $position): bool => $original->unbox()[$position] !== $candidate->unbox()[$position])), $candidates));
        self::assertGreaterThan(32, $count);
        self::assertLessThanOrEqual(64, $count);
    }

    public function testShrinkingRebuildsTheCompleteWorkloadAndItsExpectedRows(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(20261029);
        $quantifier = new ForAll([ScriptGenerator::tableChanges(GrammarRelease::MySql847, 'rollback')], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional workload counterexample');
        try {
            $quantifier->then(static function (array $sequence) use (&$attempts, &$last): void {
                $execution = new Execution((new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt'));
                foreach ($sequence as $step) {
                    self::assertIsArray($step);
                    [$statement, $expected, $code] = $step;
                    self::assertInstanceOf(Statement::class, $statement);
                    try {
                        $reply = $execution->statement($statement);
                        self::assertSame(0, $code);
                        if (is_array($expected)) {
                            self::assertInstanceOf(ResultSet::class, $reply);
                            self::assertSame($expected, $reply->rows);
                        } else {
                            self::assertInstanceOf(Completion::class, $reply);
                            self::assertSame($expected, $reply->affectedRows);
                        }
                    } catch (SqlError $error) {
                        self::assertSame($code, $error->getCode(), $error->getMessage());
                        $execution->failed($error);
                    }
                }
                $attempts++;
                $last = $sequence;
                self::fail('Intentional workload counterexample');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertIsArray($last);
            self::assertCount(10, $last);
            self::assertIsArray($last[3]);
            self::assertSame([['0']], $last[3][1]);
        }
    }
}
