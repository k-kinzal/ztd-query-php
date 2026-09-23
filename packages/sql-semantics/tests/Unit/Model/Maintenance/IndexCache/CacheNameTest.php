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

#[CoversClass(Cache\CacheName::class)]
#[Medium]
final class CacheNameTest extends TestCase
{
    public function testRetainsTheRequestedSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t IN `DEFAULT`');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        self::assertInstanceOf(Cache\CacheName::class, $statement->cache);
        self::assertSame('DEFAULT', $statement->cache->name);
    }

}
