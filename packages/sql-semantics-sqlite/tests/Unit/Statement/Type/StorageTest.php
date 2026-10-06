<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;

#[CoversClass(Storage::class)]
#[Medium]
final class StorageTest extends TestCase
{
    public function testNameIsTheUpperCaseResultOfTypeof(): void
    {
        self::assertSame('INTEGER', Storage::Integer->name());
        self::assertSame('REAL', Storage::Real->name());
        self::assertSame('TEXT', Storage::Text->name());
        self::assertSame('BLOB', Storage::Blob->name());
        self::assertSame(['INTEGER', 'REAL', 'TEXT', 'BLOB'], array_map(static fn (Storage $storage): string => $storage->name(), Storage::cases()));
    }

    public function testNameDescribesTheTypeOfALiteral(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1, 1.5, 'a', x'AB'");
        $types = array_map(static fn (Field $field): ?TypeDescriptor => $field->type instanceof Known ? $field->type->descriptor : null, $query->fields()->items ?? []);

        self::assertSame([Storage::Integer, Storage::Real, Storage::Text, Storage::Blob], $types);
    }
}
