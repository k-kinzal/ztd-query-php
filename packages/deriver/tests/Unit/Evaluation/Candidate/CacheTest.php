<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Evaluation\Candidate\Cache;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CacheTest extends TestCase
{
    public function testGetDoesNotInventMissingValues(): void
    {
        $cache = new Cache(1);
        self::assertNull($cache->get('absent'));
    }
    public function testPutEvictsWithoutMutatingPreviouslyReturnedNodes(): void
    {
        $cache = new Cache(1);
        $first = Term::constant(1);
        $cache->put('a', $first);
        $cache->put('b', Term::constant(2));
        self::assertNull($cache->get('a'));
        self::assertSame(1, $first->native());
        $second = $cache->get('b');
        self::assertNotNull($second);
        self::assertSame(2, $second->native());
    }
    public function testClearReleasesAllOwnedEntries(): void
    {
        $cache = new Cache();
        $cache->put('a', Term::constant(1));
        $cache->clear();
        self::assertSame(0, $cache->count());
    }
    public function testCountObeysZeroRetention(): void
    {
        $cache = new Cache(0);
        $cache->put('a', Term::constant(1));
        self::assertSame(0, $cache->count());
    }
    public function testReusableRejectsNestedUnfinishedDependencies(): void
    {
        $cache = new Cache();
        self::assertFalse($cache->reusable(new Term('binary', '+', [Term::constant(1),new Term('deferred', 'input')])));
        self::assertTrue($cache->reusable(Term::array([Term::constant(1)])));
    }
}
