<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownStorage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Generated::class)]
#[Medium]
final class GeneratedTest extends TestCase
{
    public function testStorageIsVirtualUnlessStoredIsWritten(): void
    {
        self::assertSame(GeneratedStorage::Virtual, (new Generated(new IntegerLiteral('1')))->storage());
        self::assertSame(GeneratedStorage::Virtual, (new Generated(new IntegerLiteral('1'), new Word(new Name('virtual'))))->storage());
        self::assertSame(GeneratedStorage::Stored, (new Generated(new IntegerLiteral('1'), new Word(new Name('Stored'))))->storage());
    }

    public function testStorageIsNullForAnyOtherWordAndForAQuotedWord(): void
    {
        self::assertNull((new Generated(new IntegerLiteral('1'), new Word(new Name('persisted'))))->storage());
        self::assertNull((new Generated(new IntegerLiteral('1'), new Word(new Name('stored'), WordQuote::Double)))->storage());
    }

    public function testDeriveConstraintResolvesColumnsOfTheTableBeingDefined(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b AS (a))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $generated = $statement->columns[1]->constraints[0];
        self::assertInstanceOf(Generated::class, $generated);
        $resolution = $operation->facts->scalar($generated->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($operation->declarations()[0]->columns[0], $resolution->slot->column);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintDoesNotSeeTheRowIdentifier(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b AS (rowid))', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveConstraintReportsAnUnknownStorageWord(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b AS (a) "stored")', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(UnknownStorage::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderDropsTheOptionalKeywordsAndKeepsTheStorageWord(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('CREATE TABLE t (a INT, b INT NOT NULL AS (a + 1) stored)', $semantics->analyze('create table t (a INT, b INT not null generated always as (a + 1) stored)')->toString());
        self::assertSame('CREATE TABLE t (a, b AS (a) "stored")', $semantics->analyze('CREATE TABLE t (a, b AS (a) "stored")')->toString());
        self::assertSame('CREATE TABLE t (a, b AS (a))', $semantics->analyze('CREATE TABLE t (a, b AS (a))')->toString());
    }
}
