<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\ExtractUnit;

#[CoversClass(ExtractUnit::class)]
#[Small]
final class ExtractUnitTest extends TestCase
{
    public function testFieldIsTheLowerCaseName(): void
    {
        self::assertSame('second', ExtractUnit::Second->field());
    }
}
