<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Exception\InvalidInputException;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use WeakReference;

#[CoversNothing]
#[Small]
final class ResultRetentionTest extends TestCase
{
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
        self::assertInstanceOf(\Deriver\Analysis\Session::class, $session);
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
}
