<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(Table::class)]
#[Small]
final class TableTest extends TestCase
{
    public function testMatchingColumnsPreservesAmbiguityAndDeclarationIdentity(): void
    {
        $first = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $second = new Column(new Name('FOO'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), $first, $second);
        self::assertSame([$first, $second], $table->matchingColumns('foo', Comparison::AsciiInsensitive));
        self::assertSame([$first], $table->matchingColumns('foo', Comparison::Sensitive));
        self::assertSame([], $table->matchingColumns('missing', Comparison::Sensitive));
    }

    public function testWithColumnRetainsOriginalDeclarationIdentities(): void
    {
        $original = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer));
        $added = new Column(new Name('name'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('users')), $original);
        $changed = $table->withColumn($added);
        self::assertNotSame($table, $changed);
        self::assertSame([$original], $table->columns);
        self::assertSame([$original, $added], $changed->columns);
        self::assertSame($table->name, $changed->name);
    }
}
