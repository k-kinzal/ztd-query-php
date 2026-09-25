<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\Histogram;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy;
use SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RefreshPolicy::class)]
#[Medium]
final class RefreshPolicyTest extends TestCase
{
    #[TestWith(['', RefreshPolicy::Default])]
    #[TestWith([' MANUAL UPDATE', RefreshPolicy::Manual])]
    #[TestWith([' AUTO UPDATE', RefreshPolicy::Automatic])]
    public function testDistinguishesTheRefreshRequests(string $suffix, RefreshPolicy $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id' . $suffix);
        self::assertInstanceOf(UpdateHistogramStatement::class, $statement);
        self::assertSame($expected, $statement->refresh);
        self::assertSame('ANALYZE TABLE `t` UPDATE HISTOGRAM ON `id`' . $suffix, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
