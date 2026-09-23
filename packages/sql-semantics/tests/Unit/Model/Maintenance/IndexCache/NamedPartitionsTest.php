<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\IndexCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Statement\Maintenance\MySql as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Cache\NamedPartitions::class)]
#[Medium]
final class NamedPartitionsTest extends TestCase
{
    public function testRetainsTheRequestedSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t PARTITION (`ALL`) IN DEFAULT');
        self::assertInstanceOf(Statement\CachePartitionIndexesStatement::class, $statement);
        self::assertInstanceOf(Cache\NamedPartitions::class, $statement->partitions);
        self::assertSame(['ALL'], $statement->partitions->names);
    }

}
