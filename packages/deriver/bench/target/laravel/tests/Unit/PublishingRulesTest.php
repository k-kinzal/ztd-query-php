<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PublishingRules;
use DomainException;
use PHPUnit\Framework\TestCase;

final class PublishingRulesTest extends TestCase
{
    public function testReviewAndEscalationPolicies(): void
    {
        $rules = new PublishingRules();
        self::assertSame('pending', $rules->initialState(true, 'member'));
        self::assertSame('published', $rules->initialState(true, 'moderator'));
        self::assertSame('locked', $rules->nextState('published', 'lock'));
        self::assertSame(4, $rules->escalationHours('privacy'));
        self::assertSame(48, $rules->escalationHours('spam'));
        $this->expectException(DomainException::class);
        $rules->nextState('archived', 'unlock');
    }
}
