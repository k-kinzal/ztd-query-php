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
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $first, $second);
        self::assertSame([$first, $second], $table->matchingColumns('foo', Comparison::AsciiInsensitive));
        self::assertSame([$first], $table->matchingColumns('foo', Comparison::Sensitive));
        self::assertSame([], $table->matchingColumns('missing', Comparison::Sensitive));
    }

    public function testMatchingColumnsLetsExplicitDeclarationsShadowOnlyTheirOwnRowidSpelling(): void
    {
        $rowid = new SqliteRowIdentifier();
        $declared = new Column(new Name('rowid'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared, $rowid);
        self::assertSame([$declared], $table->columns);
        self::assertSame([$declared], $table->matchingColumns('ROWID', Comparison::AsciiInsensitive));
        self::assertSame([$rowid->column], $table->matchingColumns('_rowid_', Comparison::AsciiInsensitive));
        self::assertSame([$rowid->column], $table->matchingColumns('oid', Comparison::AsciiInsensitive));
    }

    public function testOwnsColumnRequiresIdentityAndIncludesItsImplicitRowIdentifier(): void
    {
        $rowid = new SqliteRowIdentifier();
        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Text));
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $declared, $rowid);
        self::assertTrue($table->ownsColumn($declared));
        self::assertTrue($table->ownsColumn($rowid->column));
        self::assertFalse($table->ownsColumn(new Column($rowid->column->name, $rowid->column->type, $rowid->column->nullability)));
    }

}
