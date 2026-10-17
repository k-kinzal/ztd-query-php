<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Deriver\Evidence\Node;
use Deriver\Exception\InvalidInputException;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Result\Candidate;
use Deriver\Result\CandidateCollection;
use Deriver\Session\ResultRetention;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\CandidateApi;
use WeakReference;

#[CoversNothing]
#[Small]
final class ResultRetentionTest extends TestCase
{
    public function testSmallIncludesPrivateCandidatePayloads(): void
    {
        $retention = new ResultRetention();
        $evidence = [CandidateApi::proof(new Node('literal'))];
        $small = new CandidateCollection([new Candidate(Term::constant(42), $evidence)], new ResultRef('small'));
        self::assertTrue($retention->small($small));
        $large = new CandidateCollection([new Candidate(Term::constant(str_repeat('x', 1048577)), $evidence)], new ResultRef('large'));
        self::assertFalse($retention->small($large));
        $weak = WeakReference::create($large);
        $retention->remember($large);
        unset($large);
        self::assertNull($weak->get());
    }

    public function testSmallIncludesPrivateCandidateEntries(): void
    {
        $candidate = new Candidate(Term::constant(42), [CandidateApi::proof(new Node('literal'))]);
        $collection = new CandidateCollection(array_fill(0, 4097, $candidate), new ResultRef('many'));
        self::assertFalse((new ResultRetention())->small($collection));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testRememberReleasesOldResultsWhileTheSessionRemainsAlive(): void
    {
        $session = Analysis::session('<?php function target(){return 7;}');
        $first = $session->derive(new ReturnQuery('target'));
        $weak = WeakReference::create($first);
        $reference = $first->reference;
        unset($first);
        array_map(static function (int $i) use ($session): void {
            $session->derive(new ReturnQuery('target', budget: new Budget(transfers: 1000 + $i)));
        }, range(1, 64));
        gc_collect_cycles();
        self::assertNull($weak->get());
        self::assertCount(32, $session->results);
        self::assertCount(32, $session->cache);
        $this->expectException(InvalidInputException::class);
        $session->explain($reference);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testCallerOwnedResultsStayExplainableAfterWorkingSetEviction(): void
    {
        $session = Analysis::session('<?php function target(){return 7;}');
        $first = $session->derive(new ReturnQuery('target'));
        array_map(static function (int $i) use ($session): void {
            $session->derive(new ReturnQuery('target', budget: new Budget(transfers: 1000 + $i)));
        }, range(1, 40));
        self::assertSame($first->evidence, $session->explain($first->reference)->nodes);
        self::assertSame($first, $session->derive(new ReturnQuery('target')));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testSmallRejectsLargeLiteralPayloadsWithoutRetainingThem(): void
    {
        $session = Analysis::session('<?php function target(){return "' . str_repeat('x', 1048577) . '";}');
        $result = $session->derive(new ReturnQuery('target'));
        self::assertNotNull($result->definite());
        $weak = WeakReference::create($result);
        self::assertSame($result->evidence, $session->explain($result->reference)->nodes);
        unset($result);
        gc_collect_cycles();
        self::assertNull($weak->get());
        $again = $session->derive(new ReturnQuery('target'));
        $value = $again->normalOutcomes[0]->values['return']->native();
        self::assertIsString($value);
        self::assertSame(1048577, strlen($value));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testRepeatedBatchIdentityRemainsExplainableWhileAnEarlierResultIsHeld(): void
    {
        $session = Analysis::session('<?php function target(){return "' . str_repeat('x', 1048577) . '";}');
        $query = new ReturnQuery('target');
        $first = $session->deriveTogether([$query])->results[0];
        $later = $session->deriveTogether([$query])->results[0];
        self::assertSame($first->reference->id, $later->reference->id);
        $weak = WeakReference::create($later);
        unset($later);
        gc_collect_cycles();
        self::assertNull($weak->get());
        self::assertSame($first->evidence, $session->explain($first->reference)->nodes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testClearReleasesRecentResults(): void
    {
        $retention = new ResultRetention();
        $result = Analysis::returns('<?php function target(){return 1;}');
        $weak = WeakReference::create($result);
        $retention->remember($result);
        unset($result);
        $retention->clear();
        gc_collect_cycles();
        self::assertNull($weak->get());
    }
}
