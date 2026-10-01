<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class SessionContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testQueryIdentityIncludesArgumentsScopeAndBudget(): void
    {
        $session = Analysis::session('<?php function target($x){return $x;}');
        $one = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant(1)])])));
        $two = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant(2)])])));
        $small = $session->derive(new ReturnQuery('target', budget: new Budget(transfers: 1)));
        self::assertNotSame($one->reference->id, $two->reference->id);
        self::assertNotSame($one->reference->id, $small->reference->id);
        self::assertSame(1, $one->normalOutcomes[0]->values['return']->native());
        self::assertSame(2, $two->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testColdAndWarmQueriesRetainTheSameLogicalCost(): void
    {
        $session = Analysis::session('<?php function target(){return 42;}function unrelated(){return 100;}');
        $cold = $session->derive(new ReturnQuery('target'));
        $session->derive(new ReturnQuery('unrelated'));
        $warm = $session->derive(new ReturnQuery('target'));
        self::assertSame($cold, $warm);
        self::assertSame(1, $warm->statistics->graphs);
        self::assertSame($cold->statistics->transfers, $warm->statistics->transfers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallSelectionDoesNotCompileUnrelatedFunctions(): void
    {
        $session = Analysis::session('<?php function selected(){observe(42);}function unrelated(){return 100;}');
        $site = $session->callsTo('observe')[0];
        $result = $session->derive(new ValueQuery($site->argument(0)));
        self::assertSame(42, $result->normalOutcomes[0]->values['value']->native());
        self::assertSame(1, $result->statistics->graphs);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFabricatedRegistersAreInvalidInsteadOfUnreachable(): void
    {
        $session = Analysis::session('<?php function target(){observe(42);}');
        $site = $session->callsTo('observe')[0];
        $this->expectException(InvalidInputException::class);
        $session->derive(new ValueQuery(new ExpressionRef($site->argument(0)->source, $site->callable, 'missing-register')));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testProvenUnreachableObservationHasNoResidualSuccess(): void
    {
        $session = Analysis::session('<?php function target(){if(false){observe(42);}}');
        $result = $session->derive(new ValueQuery($session->callsTo('observe')[0]->argument(0)));
        self::assertSame('unreachable', $result->reachability);
        self::assertSame([], $result->normalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnrelatedSyntaxErrorsDoNotInvalidateKnownReturns(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('good.php', '<?php function target(){return 42;}'), new SourceFile('bad.php', '<?php function broken(')]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(42, $result->normalOutcomes[0]->values['return']->native());
        self::assertCount(1, $result->projectDiagnostics);
        self::assertSame([], $result->frontiers);
        self::assertSame('closed', $result->assessment->closure);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFileOrderDoesNotChangeSnapshotOrSemanticResult(): void
    {
        $a = new SourceFile('a.php', '<?php function target(){return helper();}');
        $b = new SourceFile('b.php', '<?php function helper(){return 42;}');
        $first = (new Analyzer())->open(new ProjectInput([$a, $b]));
        $second = (new Analyzer())->open(new ProjectInput([$b, $a]));
        $one = $first->derive(new ReturnQuery('target'));
        $two = $second->derive(new ReturnQuery('target'));
        self::assertSame($first->snapshot()->id, $second->snapshot()->id);
        self::assertSame($one->reference->id, $two->reference->id);
        self::assertEquals($one->normalOutcomes, $two->normalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSourceCaptureDoesNotExecuteTopLevelCode(): void
    {
        $session = Analysis::session('<?php throw new RuntimeException("Never execute this file");exit(99);function target(){return 42;}');
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(42, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSourceAndWorldChangesInvalidateResults(): void
    {
        $first = Analysis::session('<?php function target(){return 1;}');
        $second = Analysis::session('<?php function target(){return 2;}');
        $closed = Analysis::session('<?php function target(){return 1;}', new Configuration(closedWorld: true));
        self::assertNotSame($first->snapshot()->id, $second->snapshot()->id);
        self::assertNotSame($first->snapshot()->id, $closed->snapshot()->id);
        self::assertSame(2, $second->derive(new ReturnQuery('target'))->normalOutcomes[0]->values['return']->native());
    }
}
