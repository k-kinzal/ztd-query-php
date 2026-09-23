<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AllForeignTables::class)]
#[Medium]
final class AllForeignTablesTest extends TestCase
{
    public function testImplicitSelectionTargetsTheWholeRemoteSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        self::assertSame(AllForeignTables::InSchema, $statement->selection);
        self::assertSame('all', $statement->selection->value);
    }

}
