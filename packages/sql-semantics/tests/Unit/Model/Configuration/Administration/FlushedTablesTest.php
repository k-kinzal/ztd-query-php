<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\FlushedTables;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FlushedTables::class)]
#[Medium]
final class FlushedTablesTest extends TestCase
{
    public function testCheckReturnsTheTablesAndRequiresOneWhenAsked(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t');
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertSame($statement->tables, FlushedTables::check($statement->origin, $statement->tables, 'FLUSH', true));
        self::assertSame([], FlushedTables::check($statement->origin, [], 'FLUSH'));
        $this->expectException(InvalidStructure::class);
        FlushedTables::check($statement->origin, [], 'FLUSH', true);
    }
}
