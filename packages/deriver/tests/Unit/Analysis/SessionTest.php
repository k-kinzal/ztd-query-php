<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class SessionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveKeepsPartialExpressions(): void
    {
        $result = A::returns('function target($x){return 1+$x;}');
        self::assertSame('partials', $result->candidates[0]->type);
        self::assertSame(1, $result->candidates[0]->term->operands[0]->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveManyPreservesIndependentOrder(): void
    {
        $session = A::session('function target(){return 1;}function other(){return 2;}');
        $results = $session->deriveMany([new ReturnQuery('other'),new ReturnQuery('target')]);
        self::assertSame([2,1], array_map(static fn ($result) => $result->candidates[0]->result, $results->results));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeriveTogetherReusesACompletedQuery(): void
    {
        $session = A::session();
        $query = new ReturnQuery('target');
        $results = $session->deriveTogether([$query,$query]);
        self::assertSame($results->results[0], $results->results[1]);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testReleaseKeepsCallerOwnedProofs(): void
    {
        $session = A::session();
        $result = $session->derive(new ReturnQuery('target'));
        $before = $result->toJson();
        $session->release();
        self::assertSame($before, $result->toJson());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testRememberAndExplainKeepCandidateEvidence(): void
    {
        $session = A::session();
        $result = $session->derive(new ReturnQuery('target'));
        $session->remember($result);
        self::assertSame($result->candidates[0]->evidence, $session->explain($result->reference));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testExplainRejectsForeignReferences(): void
    {
        $session = A::session();
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        $session->explain(new \Deriver\Reference\ResultRef('absent'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSnapshotCapturesFileHash(): void
    {
        $session = A::session();
        self::assertSame(hash('sha256', '<?php function target(){return 42;}'), $session->snapshot()->sources['candidate.php']);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCallsToKeepsArgumentSource(): void
    {
        $session = A::session('function target(){observe(42);}');
        self::assertSame('target', $session->callsTo('observe')[0]->callable);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeclarationsExposeCapturedSignature(): void
    {
        self::assertContains('target', A::session()->declarations()->symbols());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCommentsPreserveStatementText(): void
    {
        self::assertSame('/** marker */', A::session('function target(){/** marker */ return 42;}')->comments('target')[0]->text);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEntrypointsRequireExplicitProviders(): void
    {
        self::assertSame([], A::session()->entrypoints());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testObservationsRequireExplicitProviders(): void
    {
        self::assertSame([], A::session()->observations());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testExpressionSelectsExactLiteralBytes(): void
    {
        $session = A::session();
        $reference = $session->expression('candidate.php', 31, 33);
        self::assertSame(42, $session->derive(new \Deriver\Query\ValueQuery($reference))->candidates[0]->result);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValidateSourceRejectsMalformedInput(): void
    {
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        A::session('function broken(');
    }

}
