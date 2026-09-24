<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\HelpStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(HelpStatement::class)]
#[Medium]
final class HelpStatementTest extends TestCase
{
    public function testWithOriginRetainsTheTopic(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('HELP `select`');
        self::assertInstanceOf(HelpStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('select', $copy->topic);
        self::assertSame("HELP 'select'", $copy->toString());
    }

    public function testWithTopicSearchesAnotherTopicImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("HELP 'contents'");
        self::assertInstanceOf(HelpStatement::class, $statement);
        $changed = $statement->withTopic("it's");
        self::assertSame("HELP 'it''s'", $changed->toString());
        self::assertSame("it's", $changed->topic);
        self::assertSame('contents', $statement->topic);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("HELP 'contents'");
        $this->expectException(InvalidStructure::class);
        new HelpStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), 'contents');
    }
}
