<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TlsRequirement::class)]
#[Medium]
final class TlsRequirementTest extends TestCase
{
    public function testRenderWritesEveryKind(): void
    {
        self::assertSame('CREATE USER u REQUIRE SSL', (new Inspection())->inspect('create user u require ssl')->toString());
        self::assertSame("CREATE USER u REQUIRE SUBJECT 's' AND CIPHER 'c'", (new Inspection())->inspect("create user u require subject 's' cipher 'c'")->toString());
    }
}
