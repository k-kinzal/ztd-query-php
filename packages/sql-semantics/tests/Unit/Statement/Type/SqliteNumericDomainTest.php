<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Type\SqliteNumericDomain;

#[CoversClass(SqliteNumericDomain::class)]
#[Small]
final class SqliteNumericDomainTest extends TestCase
{
    public function testStorageClassesExcludesTextAndBinaryResults(): void
    {
        self::assertSame([Builtin::Integer, Builtin::DoublePrecision], SqliteNumericDomain::IntegerOrReal->storageClasses());
    }
}
