<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Routine\CharacteristicBinder::class)]
#[Medium]
final class CharacteristicBinderTest extends TestCase
{
    public function testBindRetainsTheLastDeclarationForEachIndependentRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER FUNCTION f COMMENT 'first' NO SQL SQL SECURITY DEFINER LANGUAGE JavaScript COMMENT 'last' MODIFIES SQL DATA SQL SECURITY INVOKER LANGUAGE SQL");
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        self::assertSame("'last'", $statement->changes->comment?->text);
        self::assertSame(SqlDataAccess::Modifies, $statement->changes->dataAccess);
        self::assertSame(RoutineSecurity::Invoker, $statement->changes->security);
        self::assertSame('SQL', $statement->changes->language);
    }

    public function testBindKeepsCommentContentsSeparateFromMetadataKeywords(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER PROCEDURE p COMMENT 'NO SQL, SQL SECURITY DEFINER'");
        self::assertInstanceOf(AlterProcedureStatement::class, $statement);
        self::assertNull($statement->changes->dataAccess);
        self::assertNull($statement->changes->security);
        self::assertSame("'NO SQL, SQL SECURITY DEFINER'", $statement->changes->comment?->text);
    }

    public function testBindNormalizesTheSqlLanguageKeywordAndIdentifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $identifier = $binder->bind('ALTER FUNCTION f LANGUAGE `sQl`');
        $keyword = $binder->bind('ALTER FUNCTION f LANGUAGE SQL');
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($identifier), (new \SqlSemantics\SimpleSerializer())->serialize($keyword));
    }

    public function testBindRejectsAnEmptyLanguageName(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION f LANGUAGE ``');
    }
}
