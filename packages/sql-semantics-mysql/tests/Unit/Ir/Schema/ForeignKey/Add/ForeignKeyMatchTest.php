<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyMatch;

#[CoversClass(ForeignKeyMatch::class)]
#[Small]
final class ForeignKeyMatchTest extends TestCase
{
    public function testPartialRemainsADeclaredNativeMode(): void
    {
        self::assertSame('PARTIAL', ForeignKeyMatch::Partial->value);
    }
}
