<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Domain;

use Deriver\Model\Domain\DomainFact;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainFact::class)]
#[UsesClass(Term::class)]
#[Small]
final class DomainFactTest extends TestCase
{
    public function testTermPreservesTheSemanticContract(): void
    {
        $fact = new DomainFact('policy', Term::fromNative(['ttl' => 10, 'tags' => ['a']]));
        self::assertSame('policy', $fact->domain);
        self::assertSame(['ttl' => 10, 'tags' => ['a']], $fact->representation->native());
    }
}
