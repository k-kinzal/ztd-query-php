<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Kind::class)]
#[Medium]
final class KindTest extends TestCase
{
    public function testRepresentsEveryIndexKind(): void
    {
        self::assertSame(['ordinary', 'fulltext', 'spatial'], array_column(Kind::cases(), 'value'));
    }

    public function testClassifiesFullTextAndOrdinaryIndexes(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, body TEXT); CREATE FULLTEXT INDEX ft ON t(body); CREATE INDEX ix ON t(id)')->tables[0];
        self::assertSame([Kind::FullText, Kind::Ordinary], array_map(static fn ($index): Kind => $index->properties->kind, $table->indexes));
    }
}
