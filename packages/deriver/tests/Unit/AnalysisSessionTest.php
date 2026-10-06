<?php

declare(strict_types=1);

namespace Tests\Unit;

use Deriver\Analysis\Session;
use Deriver\AnalysisSession;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class AnalysisSessionTest extends TestCase
{
    public function testDerivePreservesTheSemanticContract(): void
    {
        self::assertContains(AnalysisSession::class, class_implements(Session::class));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveManyKeepsRequestOrderAndIndependentResults(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function one(){return 1;}function two(){return 2;}');
        $results = $session->deriveMany([new ReturnQuery('two'), new ReturnQuery('one')]);
        self::assertSame(2, $results->results[0]->normalOutcomes[0]->values['return']->native());
        self::assertSame(1, $results->results[1]->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplainFindsTheDerivedResultAndRejectsForeignIds(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 1;}');
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame($result->evidence, $session->explain($result->reference)->nodes);
        $this->expectException(InvalidInputException::class);
        $session->explain(new ResultRef('foreign'));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotCapturesSourceHashes(): void
    {
        $source = '<?php function target(){return 1;}';
        $session = \Tests\Fake\Analysis::session($source);
        self::assertSame(['fixture.php' => hash('sha256', $source)], $session->snapshot()->sources);
        self::assertSame($session->snapshot(), $session->snapshot());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallsToPreservesArgumentPositions(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){observe("first", 2);}');
        $sites = $session->callsTo('observe');
        self::assertCount(1, $sites);
        self::assertCount(2, $sites[0]->arguments);
        self::assertSame('target', $sites[0]->callable);
        self::assertNotSame($sites[0]->arguments[0]->register, $sites[0]->arguments[1]->register);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointsUsesOnlyExplicitProviderContributions(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        self::assertSame('entry', $session->entrypoints()[0]->symbol);
        self::assertSame([], \Tests\Fake\Analysis::session('<?php function entry(){}')->entrypoints());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsCreatesQueriesUsingTheCapturedEntries(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){return 1;}', new Configuration(providers: [new \Tests\Fake\MiniContainer()]));
        $query = $session->observations()['entry-return'];
        self::assertSame('entrypoint', $query->scope()->mode);
        self::assertSame('entry', $query->scope()->entries[0]->symbol);
    }
    /**
     * @throws JsonException If the declaration snapshot cannot be encoded
     */
    public function testDeclarationsExposeCapturedSignatures(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(string $sql){}');
        $signature = $session->declarations()->signature('target');
        self::assertNotNull($signature);
        self::assertSame('sql', $signature->parameters[0]->name);
    }

    /**
     * @throws JsonException If the captured metadata cannot be encoded
     */
    public function testCommentsDoNotInterpretAnnotationsAsTypes(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function f(){/** @var PDO $db */ global $db;}');
        self::assertSame('/** @var PDO $db */', $session->comments('f')[0]->text);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testDeriveTogetherPreservesReturnQueriesInOutputOrder(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(){return 8;}');
        $results = $session->deriveTogether([new ReturnQuery('target'), new ReturnQuery('target')])->results;
        self::assertCount(2, $results);
        self::assertSame(8, $results[0]->normalOutcomes[0]->values['return']->native());
        self::assertSame($results[1]->evidence, $session->explain($results[1]->reference)->nodes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReleaseKeepsCallerOwnedResultsImmutable(): void
    {
        $s = \Tests\Semantic\CandidateContractTest::session('function target(){return 1;}');
        $r = $s->derive(new ReturnQuery('target'));
        $s->release();
        self::assertSame(1, $r->candidates[0]->term->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testExpressionSelectsAnExactSourceRange(): void
    {
        $session = \Tests\Fake\CandidateApi::session('function target(){return 42;}');
        $expression = $session->expression('candidate.php', 31, 33);
        self::assertSame(42, $session->derive(new \Deriver\Query\ValueQuery($expression))->candidates[0]->result);
    }

}
