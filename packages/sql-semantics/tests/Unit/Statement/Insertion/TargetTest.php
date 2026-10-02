<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Insertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Insertion\Arity;
use SqlSemantics\Statement\Insertion\Target;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

#[CoversClass(Target::class)]
#[Small]
final class TargetTest extends TestCase
{
    public function testToStringKeepsExplicitBindingsAndOriginalDeclarations(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $target = new Target(new TableReference($catalog, $table->name), new Name('foo'));
        self::assertSame('bar (foo)', $target->toString());
        self::assertNotNull($target->columns);
        self::assertInstanceOf(ResolvedColumn::class, $target->columns[0]->resolution);
        self::assertSame($column, $target->columns[0]->resolution->column);
        self::assertSame($table, $target->columns[0]->resolution->table);
        self::assertSame(Arity::Matching, $target->arity(1, 1));
    }

    public function testArityResolvesImplicitColumnsWithoutPrintingAColumnList(): void
    {
        $column = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('bar')), new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $target = new Target(new TableReference($catalog, $table->name));
        self::assertSame('bar', $target->toString());
        self::assertFalse($target->explicitColumns);
        self::assertSame(Arity::Mismatch, $target->arity(2));
    }

    #[TestWith([false, Arity::MissingDeclaration])]
    #[TestWith([true, Arity::MissingTable])]
    public function testArityDistinguishesAbsentMetadataFromAnAbsentTable(bool $complete, Arity $expected): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: $complete);
        $target = new Target(new TableReference($catalog, new QualifiedName(new Name('bar'))));
        self::assertNull($target->columns);
        self::assertSame($expected, $target->arity(1));
        self::assertSame(Arity::Mismatch, $target->arity(1, 2));
    }
}
