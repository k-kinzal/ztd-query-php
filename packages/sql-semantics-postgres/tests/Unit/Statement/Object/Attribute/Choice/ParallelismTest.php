<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;

#[CoversClass(Parallelism::class)]
#[Small]
final class ParallelismTest extends TestCase
{
    public function testReadComparesExactly(): void
    {
        self::assertSame([Parallelism::Safe, Parallelism::Restricted, Parallelism::Unsafe, null], [Parallelism::read('safe'), Parallelism::read('restricted'), Parallelism::read('unsafe'), Parallelism::read('SAFE')]);
    }
}
