<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Medium]
final class BatchObservationsTest extends TestCase
{
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerObservationIndices')]
    public function testInstructionSharesExecutionPrefixesAndKeepsSeparateValues(int $index): void
    {
        $source = '<?php function sink($x){} function target(){$n=0;' . str_repeat('$n+=1;', 100) . 'sink($n);$n++;sink($n);$n++;sink($n);}';
        $session = Analysis::session($source);
        $queries = array_map(static fn ($site) => new ValueQuery($site->argument(0)), $session->callsTo('sink'));
        $independent = $session->deriveMany($queries)->results;
        $batch = $session->deriveTogether($queries)->results;
        self::assertCount(3, $batch);
        $result = $batch[$index];
        self::assertNotNull($result->definite());
        self::assertSame(100 + $index, $result->definite()->values['value']->native());
        self::assertSame($independent[$index]->normalOutcomes[0]->values['value']->native(), $result->normalOutcomes[0]->values['value']->native());
        self::assertNotSame($independent[$index]->reference->id, $result->reference->id);
        self::assertSame($result->evidence, $session->explain($result->reference)->nodes);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($result->toJson()));
        self::assertLessThan(array_sum(array_map(static fn ($result) => $result->statistics->transfers, $independent)) / 2, $batch[0]->statistics->transfers);
        self::assertSame($batch[0]->statistics->transfers, $batch[2]->statistics->transfers);
        self::assertSame($independent[0], $session->derive($queries[0]));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testBatchPreservesTupleCorrelationStateAndReturn(): void
    {
        $session = Analysis::session('<?php function sink($a,$b){} function target(bool $flag){$a=$flag?1:2;$b=$a+10;sink($a,$b);return $a;}');
        $site = $session->callsTo('sink')[0];
        $results = $session->deriveTogether([
            new TupleQuery($site->beforeInvocation(), ['a' => $site->argument(0), 'b' => $site->argument(1)]),
            new StateQuery($site->beforeInvocation(), 'a'),
            new ReturnQuery('target'),
        ])->results;
        $pairs = array_map(static fn ($outcome) => [$outcome->values['a']->native(), $outcome->values['b']->native()], $results[0]->normalOutcomes);
        sort($pairs);
        self::assertSame([[1,11],[2,12]], $pairs);
        self::assertCount(2, $results[1]->normalOutcomes);
        self::assertCount(2, $results[2]->normalOutcomes);
        self::assertSame([], $results[0]->frontiers);
    }

    /**
     * @param string $body Body under symbolic inputs
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerControlFlow')]
    public function testCompletionAgreesWithIndependentControlFlow(string $body, int $index): void
    {
        $session = Analysis::session('<?php function sink($x){} function target(bool $flag){' . $body . '}');
        $queries = array_map(static fn ($site) => new ValueQuery($site->argument(0)), $session->callsTo('sink'));
        $independent = $session->deriveMany($queries)->results;
        $batch = $session->deriveTogether($queries)->results;
        $result = $batch[$index];
        $expected = array_map(static fn ($outcome) => $outcome->values['value']->native(), $independent[$index]->normalOutcomes);
        $actual = array_map(static fn ($outcome) => $outcome->values['value']->native(), $result->normalOutcomes);
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
        self::assertSame(count($independent[$index]->exceptionalOutcomes), count($result->exceptionalOutcomes));
        self::assertSame($independent[$index]->reachability, $result->reachability);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerControlFlow(): iterable
    {
        $cases = [
            'branch' => ['if($flag){sink(1);}else{sink(2);}sink(3);', 3],
            'throw after first' => ['sink(1);throw new Exception;sink(2);', 2],
            'branch exception' => ['if($flag){sink(1);}throw new Exception;sink(2);', 2],
            'loop' => ['for($i=0;$i<3;$i++){sink($i);}sink(9);', 2],
            'finally' => ['try{sink(1);if($flag){throw new Exception;}}finally{sink(2);}sink(3);', 3],
            'unreachable' => ['return;sink(1);sink(2);', 2],
        ];
        foreach ($cases as $name => [$body, $count]) {
            for ($i = 0; $i < $count; $i++) {
                yield $name . ':' . $i => [$body, $i];
            }
        }
    }

    /**
     * @return list<array{int}>
     */
    public static function providerObservationIndices(): array
    {
        return [[0], [1], [2]];
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerInterruptedIndices')]
    public function testSynchronizeKeepsSharedInterruptionAndRecoveredTypes(int $index): void
    {
        $session = Analysis::session('<?php function target(PDO $pdo){heavy();$pdo->query("SELECT 1");$pdo->query("SELECT 2");}', new Configuration(resources: new ResourceLimits(seconds: 0.000000001)));
        $queries = array_map(static function ($site): TupleQuery {
            self::assertNotNull($site->receiver);
            return new TupleQuery($site->beforeInvocation(), ['sql' => $site->argument(0), 'receiver' => $site->receiver]);
        }, $session->callsTo('query'));
        $result = $session->deriveTogether($queries)->results[$index];
        self::assertSame('SELECT ' . ($index + 1), $result->normalOutcomes[0]->values['sql']->native());
        self::assertSame('PDO', $result->normalOutcomes[0]->values['receiver']->attributes['type']);
        self::assertContains('TIME_LIMIT', array_column($result->frontiers, 'code'));
        self::assertNull($result->definite());
    }

    /**
     * @return list<array{int}>
     */
    public static function providerInterruptedIndices(): array
    {
        return [[0], [1]];
    }

    /**
     * @param ReturnQuery $other Incompatible query
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerMismatches')]
    public function testMismatchedExecutionContractsAreRejected(ReturnQuery $other): void
    {
        $session = Analysis::session('<?php function first(){return 1;}function second(){return 2;}');
        $this->expectException(InvalidInputException::class);
        $session->deriveTogether([new ReturnQuery('first'), $other]);
    }

    /**
     * @return list<array{ReturnQuery}>
     */
    public static function providerMismatches(): array
    {
        return [[new ReturnQuery('second')], [new ReturnQuery('first', QueryScope::fromEntrypoints([new EntryPoint('first')]))], [new ReturnQuery('first', budget: new Budget(transfers: 1))]];
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testEmptyAndDuplicateBatchesKeepRequestedOrdering(): void
    {
        $session = Analysis::session('<?php function target(){return 3;}');
        self::assertSame([], $session->deriveTogether([])->results);
        $result = $session->deriveTogether([new ReturnQuery('target'), new ReturnQuery('target')])->results;
        self::assertCount(2, $result);
        self::assertEquals($result[0]->normalOutcomes, $result[1]->normalOutcomes);
        self::assertSame(3, $result[0]->normalOutcomes[0]->values['return']->native());
    }
}
