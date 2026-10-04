<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable::class)]
#[Medium]
final class PublicationTableTest extends TestCase
{
    public function testIntroducedWhenTableIsWritten(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE a')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Publication\CreatePublication::class, $statement);
        self::assertSame(true, $statement->objects[0]->introduced());
    }

    public function testBareOfAContinuedName(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE a, b')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Publication\CreatePublication::class, $statement);
        $table = $statement->objects[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable::class, $table);
        self::assertTrue($table->bare());
    }

    public function testBareIsFalseForAStar(): void
    {
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), [], null, false, true))->bare());
    }

    public function testRenderKeepsTheStarAfterASchema(): void
    {
        self::assertSame('CREATE PUBLICATION p FOR TABLES IN SCHEMA s, t *', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA s, t *')->toString());
    }

    public function testRenderWritesColumnsAndFilter(): void
    {
        self::assertSame('CREATE PUBLICATION p FOR TABLE ONLY t (a, b) WHERE (a > 1)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE ONLY t (a, b) WHERE (a > 1)')->toString());
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)], [], true);
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE t (a) WHERE (a > 0)', [$t]);
        $statement = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Publication\CreatePublication::class, $statement);
        $resolution = $operation->facts->relation($statement->objects[0])->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $resolution);
        self::assertSame($t, $resolution->table);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRejectsOnlyWithAStar(): void
    {
        $this->expectExceptionMessage('A table name is written with ONLY or with a trailing star, not both.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), true), [], null, true, true);
    }
}
