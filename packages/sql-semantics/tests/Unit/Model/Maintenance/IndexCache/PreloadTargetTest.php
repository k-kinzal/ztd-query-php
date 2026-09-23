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

#[CoversClass(Cache\PreloadTarget::class)]
#[Medium]
final class PreloadTargetTest extends TestCase
{
    public function testRetainsTheLeafPagePolicyWithoutLoadingValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('LOAD INDEX INTO CACHE t IGNORE LEAVES');
        self::assertInstanceOf(Statement\PreloadTableIndexesStatement::class, $statement);
        $target = $statement->targets[0];
        self::assertTrue($target->ignoreLeaves);
        self::assertNull($target->indexes->indexes);
        self::assertSame('t', $target->indexes->table->declaration->name);
    }

}
