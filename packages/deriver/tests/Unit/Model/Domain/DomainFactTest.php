<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DomainFactTest extends TestCase
{
    public function testTermPreservesTheSemanticContract(): void
    {
        $fact = new \Deriver\Model\Domain\DomainFact('policy', \Deriver\Value\Term::fromNative(['ttl' => 10, 'tags' => ['a']]));
        self::assertSame('policy', $fact->domain);
        self::assertSame(['ttl' => 10, 'tags' => ['a']], $fact->representation->native());
    }
}
