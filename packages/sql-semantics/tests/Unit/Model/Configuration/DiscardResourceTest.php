<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\DiscardResource;
use SqlSemantics\Model\Statement\Configuration\DiscardStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DiscardResource::class)]
#[Medium]
final class DiscardResourceTest extends TestCase
{
    public function testRepresentsEveryDiscardableResource(): void
    {
        self::assertSame(['ALL', 'PLANS', 'SEQUENCES', 'TEMPORARY'], array_column(DiscardResource::cases(), 'value'));
    }

    #[TestWith(['DISCARD TEMP', DiscardResource::TemporaryTables, 'DISCARD TEMPORARY'])]
    #[TestWith(['DISCARD SEQUENCES', DiscardResource::Sequences, 'DISCARD SEQUENCES'])]
    #[TestWith(['DISCARD ALL', DiscardResource::All, 'DISCARD ALL'])]
    public function testClassifiesTheReleasedResource(string $sql, DiscardResource $resource, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(DiscardStatement::class, $statement);
        self::assertSame($resource, $statement->resource);
        self::assertSame($expected, $statement->toString());
    }
}
