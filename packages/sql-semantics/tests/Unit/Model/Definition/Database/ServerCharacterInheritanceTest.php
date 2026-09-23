<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;

#[CoversClass(ServerCharacterInheritance::class)]
#[Medium]
final class ServerCharacterInheritanceTest extends TestCase
{
    public function testCasesDefineOnlyTheApplicablePolicies(): void
    {
        self::assertSame([ServerCharacterInheritance::Inherit], ServerCharacterInheritance::cases());
    }
}
