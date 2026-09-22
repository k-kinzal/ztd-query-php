<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TableLikeBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Table\CreateTableLikeStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableLikeBinder::class)]
final class TableLikeBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindPreservesAnUnresolvedTemplateRatherThanCreatingAnEmptyDefinition(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('CREATE TABLE copied (LIKE app.original)', strict: false);
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        self::assertFalse($statement->template->declaration->resolved);
        self::assertSame(['app','original'], $statement->template->name->parts);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
        self::assertSame('CREATE TABLE `copied` LIKE `app`.`original`', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString(), strict: false)->toString());
    }

    public function testBindDoesNotConfuseACheckPredicateWithATableCopy(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE parent(id INTEGER PRIMARY KEY)');
        $statement = (new Binder($schema))->bind("CREATE TABLE child(id INTEGER, name TEXT CHECK(name LIKE 'a%'), FOREIGN KEY(id) REFERENCES parent(id))");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertCount(2, $statement->definition->table->columns);
        self::assertCount(2, $statement->definition->table->constraints);
    }
}
