<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ColumnName::class)]
#[Small]
final class ColumnNameTest extends TestCase
{
    public function testRenderWritesTheColumnAlone(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ColumnName(new Name('a')))->render($out);

        self::assertSame('a', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheTableQualifier(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ColumnName(new Name('a'), new QualifiedName(new Name('t'))))->render($out);

        self::assertSame('t.a', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheDatabaseAndTableQualifiersAndQuotesWhatNeedsIt(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ColumnName(new Name('a b'), new QualifiedName(new Name('t'), new Name('db'))))->render($out);

        self::assertSame('db.t.`a b`', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredQualifiedColumnNameKeepsTheQualifierAsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $full = $lowering->names->columnName($parser->parse('CREATE TABLE t (db.t.a INT)')->find('field_ident')[0]);
        $table = $lowering->names->columnName($parser->parse('CREATE TABLE t (t.`a b` INT)')->find('field_ident')[0]);
        $out = new Output($platform->codec($profile));
        $full->render($out);

        self::assertSame('a', $full->column->value);
        self::assertSame('t', $full->table?->name->value);
        self::assertSame('db', $full->table->schema?->value);
        self::assertSame('db.t.a', (new Lexical())->join($out->pieces()));
        self::assertSame('a b', $table->column->value);
        self::assertSame('t', $table->table?->name->value);
        self::assertNull($table->table->schema);
    }

    public function testRejectsACatalogQualifier(): void
    {
        $this->expectExceptionMessage('A column name is qualified by a table and at most a database.');

        new ColumnName(new Name('a'), new QualifiedName(new Name('t'), new Name('db'), new Name('catalog')));
    }
}
