<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Declarations;

#[CoversClass(Declarations::class)]
#[Small]
final class DeclarationsTest extends TestCase
{
    public function testCasesTellCompleteFromPartialDeclarations(): void
    {
        self::assertSame([Declarations::Complete, Declarations::Partial], Declarations::cases());
    }
}
