<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\TypeNameAffinity;

#[CoversClass(TypeNameAffinity::class)]
#[Small]
final class TypeNameAffinityTest extends TestCase
{
    public function testConversionUsesNativeSubstringPriority(): void
    {
        $affinity = new TypeNameAffinity();

        self::assertSame('INTEGER', $affinity->conversion('BLOB CLOB /* INT */'));
        self::assertSame('TEXT', $affinity->conversion('DOUBLE CHARACTER'));
        self::assertSame('BLOB', $affinity->conversion('FLOAT BLOB'));
        self::assertSame('REAL', $affinity->conversion('dOuBlE'));
        self::assertSame('NUMERIC', $affinity->conversion('日本語'));
    }
}
