<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexField;

#[CoversClass(IndexField::class)]
#[Small]
final class IndexFieldTest extends TestCase
{
    public function testFieldKeepsTheNativeNameAndTypeTogether(): void
    {
        $field = new IndexField(new Identifier('Active'), ColumnKind::Boolean);

        self::assertSame('Active', $field->name->value);
        self::assertSame(ColumnKind::Boolean, $field->kind);
    }
}
