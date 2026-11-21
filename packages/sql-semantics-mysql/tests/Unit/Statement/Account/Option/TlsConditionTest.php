<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition;

#[CoversClass(TlsCondition::class)]
#[Medium]
final class TlsConditionTest extends TestCase
{
    public function testRenderWritesThePropertyAndValue(): void
    {
        self::assertSame("CREATE USER u REQUIRE CIPHER 'c'", (new Semantics())->inspect("create user u require cipher 'c'")->toString());
    }
}
