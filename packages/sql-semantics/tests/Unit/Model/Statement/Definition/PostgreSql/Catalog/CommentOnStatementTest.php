<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommentOnStatement::class)]
#[Medium]
final class CommentOnStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'", strict: false);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['app', 'users'])), $statement->object);
        self::assertSame("'people'", $statement->comment?->text);
        self::assertSame('COMMENT ON TABLE "app"."users" IS \'people\'', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'", strict: false);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'", strict: false);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'", strict: false);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, 'app'));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['app', 'users'])), $statement->object);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, 'app'), $changed->object);
        self::assertStringContainsString('COMMENT ON SCHEMA "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithCommentReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'", strict: false);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $changed = $statement->withComment(null);
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->comment, $statement->comment);
        self::assertEquals(null, $changed->comment);
        self::assertStringContainsString('IS NULL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsANonTextComment(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'");
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $number = \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        $statement->withComment($number);
    }

    public function testRejectsATypeAddressedByName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'");
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood'])));
    }

    #[TestWith(["COMMENT ON TABLE t IS E'x\\ny'", "COMMENT ON TABLE \"t\" IS E'x\\ny'"])]
    #[TestWith(['COMMENT ON TABLE t IS $$x$$', 'COMMENT ON TABLE "t" IS $$x$$'])]
    public function testKeepsTheOriginalSpellingOfTheText(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }
}
