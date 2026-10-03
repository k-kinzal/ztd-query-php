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

#[CoversClass(Known::class)]
#[Medium]
final class KnownTest extends TestCase
{
    public function testDescriptorIsTheTypeTheRulesDetermine(): void
    {
        $fields = (new Semantics(Dialect::Sqlite))->analyze("SELECT 'a', 1, 1.5, x'ff'")->fields();

        self::assertNotNull($fields);
        self::assertSame([Storage::Text, Storage::Integer, Storage::Real, Storage::Blob], array_map(static fn (Field $field): ?TypeDescriptor => $field->type instanceof Known ? $field->type->descriptor : null, $fields->items));
    }

    public function testDescriptorOfADeclaredColumnIsItsDeclaredType(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0];

        $type = $semantics->analyze('SELECT a FROM t', [$table])->field('a')->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertSame($table->columns[0]->type, $type->descriptor);
    }
}
