<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;

#[CoversClass(ObjectProblemKind::class)]
#[Small]
final class ObjectProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('DROP INDEX CONCURRENTLY does not support dropping multiple objects', ObjectProblemKind::ConcurrentMultiple->value);
    }
}
