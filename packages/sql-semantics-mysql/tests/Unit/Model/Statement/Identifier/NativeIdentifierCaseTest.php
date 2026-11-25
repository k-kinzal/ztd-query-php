<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

#[CoversClass(NativeIdentifierCase::class)]
#[Small]
final class NativeIdentifierCaseTest extends TestCase
{
    public function testFoldUsesNativeUnicodeCaseWithoutRemovingAccents(): void
    {
        $case = new NativeIdentifierCase();
        self::assertSame('primary élan άλφα k', $case->fold('PRİMARY ÉLAN ΆΛΦΑ K'));
        self::assertNotSame($case->fold('e'), $case->fold('É'));
    }
}
