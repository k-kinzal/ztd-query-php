<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\MissingField;

#[CoversClass(MissingField::class)]
#[Small]
final class MissingFieldTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Column f3 not found in data type record.', (new MissingField('f3', 'record'))->message());
    }
}
