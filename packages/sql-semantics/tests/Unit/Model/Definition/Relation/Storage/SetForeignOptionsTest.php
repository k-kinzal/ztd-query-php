<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Storage\SetForeignOptions::class)]
#[Medium]
final class SetForeignOptionsTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER FOREIGN TABLE t OPTIONS (ADD delimiter ',', SET header 'true', DROP quote)", strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Storage\SetForeignOptions::class, $statement->actions[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Foreign\AddForeignOption::class, $statement->actions[0]->changes[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Foreign\DropForeignOption::class, $statement->actions[0]->changes[2]);
        self::assertSame('ALTER FOREIGN TABLE "t" OPTIONS(ADD "delimiter" \',\', SET "header" \'true\', DROP "quote")', $statement->toString());
    }
}
