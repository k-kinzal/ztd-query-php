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

#[CoversClass(Cache\DefaultCache::class)]
#[Medium]
final class DefaultCacheTest extends TestCase
{
    public function testRetainsTheRequestedSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CACHE INDEX t IN DEFAULT');
        self::assertInstanceOf(Statement\CacheTableIndexesStatement::class, $statement);
        self::assertSame(Cache\DefaultCache::Instance, $statement->cache);
    }

}
