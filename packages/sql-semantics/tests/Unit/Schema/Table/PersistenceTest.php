<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Persistence::class)]
#[Medium]
final class PersistenceTest extends TestCase
{
    public function testRepresentsEveryPersistenceLevel(): void
    {
        self::assertSame(['permanent', 'temporary', 'unlogged'], array_column(Persistence::cases(), 'value'));
    }

    public function testClassifiesTemporaryUnloggedAndPermanentTables(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMPORARY TABLE a(id INTEGER); CREATE UNLOGGED TABLE b(id INTEGER); CREATE TABLE c(id INTEGER)');
        $levels = array_map(static function ($table): Persistence {
            self::assertInstanceOf(PostgreSqlProperties::class, $table->properties);
            return $table->properties->persistence;
        }, $schema->tables);
        self::assertSame([Persistence::Temporary, Persistence::Unlogged, Persistence::Permanent], $levels);
    }
}
