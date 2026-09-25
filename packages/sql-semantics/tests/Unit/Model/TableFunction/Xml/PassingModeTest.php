<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PassingMode::class)]
#[Medium]
final class PassingModeTest extends TestCase
{
    public function testRepresentsEveryPassingMode(): void
    {
        self::assertSame(['', 'BY REF', 'BY VALUE'], array_column(PassingMode::cases(), 'value'));
    }

    #[TestWith(["PASSING BY REF '<rows/>' BY VALUE", PassingMode::Reference, PassingMode::Value])]
    #[TestWith(["PASSING BY VALUE '<rows/>' BY REF", PassingMode::Value, PassingMode::Reference])]
    #[TestWith(["PASSING BY REF '<rows/>'", PassingMode::Reference, PassingMode::Default])]
    #[TestWith(["PASSING '<rows/>'", PassingMode::Default, PassingMode::Default])]
    public function testBindsInputAndOutputModesSeparately(string $clause, PassingMode $input, PassingMode $output): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT x.* FROM XMLTABLE ('/rows/row' " . $clause . ' COLUMNS n FOR ORDINALITY) AS x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(XmlTable::class, $statement->from->table);
        self::assertSame($input, $statement->from->table->inputMode);
        self::assertSame($output, $statement->from->table->outputMode);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
