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
use SqlSemantics\Statement\Schema\SqliteRowIdentifier;
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
    public function testMatchingColumnsLetsExplicitDeclarationsShadowOnlyTheirOwnRowidSpelling(): void
    {
        $rowid = new SqliteRowIdentifier();
        $declared = new Column(new Name('rowid'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), $declared, $rowid);
        self::assertSame([$declared], $table->columns);
        self::assertSame([$declared], $table->matchingColumns('ROWID', Comparison::AsciiInsensitive));
        self::assertSame([$rowid->column], $table->matchingColumns('_rowid_', Comparison::AsciiInsensitive));
        self::assertSame([$rowid->column], $table->matchingColumns('oid', Comparison::AsciiInsensitive));
    }

    public function testOwnsColumnRequiresIdentityAndIncludesItsImplicitRowIdentifier(): void
    {
        $rowid = new SqliteRowIdentifier();
        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), $declared, $rowid);
        self::assertTrue($table->ownsColumn($declared));
        self::assertTrue($table->ownsColumn($rowid->column));
        self::assertFalse($table->ownsColumn(clone $rowid->column));
    }

    public function testWithColumnPreservesRowIdentityWhenAddingAShadowingDeclaration(): void
    {
        $rowid = new SqliteRowIdentifier();
        $table = new Table(new QualifiedName(new Name('bar')), $rowid);
        $declared = new Column(new Name('rowid'), new TypeDescriptor(Builtin::Text));
        $changed = $table->withColumn($declared);
        self::assertSame($rowid, $changed->rowIdentifier);
        self::assertSame([$rowid->column], $table->matchingColumns('rowid', Comparison::AsciiInsensitive));
        self::assertSame([$declared], $changed->matchingColumns('rowid', Comparison::AsciiInsensitive));
    }

}
