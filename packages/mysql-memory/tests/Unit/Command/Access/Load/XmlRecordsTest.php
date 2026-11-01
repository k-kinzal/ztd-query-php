<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\XmlRecords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlRecords::class)]
#[Small]
final class XmlRecordsTest extends TestCase
{
    public function testReadRetainsParentValuesAndThePreviousEmptyRecord(): void
    {
        $input = '<list b="parent"><row a="1" b="x"/><row/><row a="2"/><row/></list><row/>';
        self::assertSame([['b' => 'x', 'a' => '1'], ['b' => 'x', 'a' => '1'], ['b' => 'parent', 'a' => '2'], ['b' => 'parent', 'a' => '2'], ['b' => 'parent', 'a' => '2']], (new XmlRecords())->read($input));
        self::assertSame([['a' => '1']], (new XmlRecords())->read('<item a="1"/><item/>', '<item>', ignored: 1));
    }

    public function testReadCombinesFieldFormsAndIgnoresUnclosedRows(): void
    {
        $input = '<row a="1" b="attr"><b>tag</b><field name="b">field</field></row><row a="2">';
        $record = (new XmlRecords())->read($input)[0];
        self::assertSame(['1', 'field'], [$record['a'], $record['b']]);
        self::assertSame([['a' => '3']], (new XmlRecords())->read('<!-- <row a="9"/> --><row a=1/><row a="3"/>'));
    }

    public function testOpenReadsACompletedAttributeRow(): void
    {
        $records = new XmlRecords();
        $offset = 0;
        self::assertTrue($records->open('<row a="1"/>', '', $offset, '<row>', false));
        self::assertSame(['a' => '1'], $records->snapshot);
    }

    public function testCloseUsesTheClosingRowNameEvenWithAMismatchedField(): void
    {
        self::assertSame([['a' => '1', 'b' => 'x'], ['a' => '2', 'b' => 'x']], (new XmlRecords())->read('<list><row a="1"><b>x</row><row a="2"/></list>'));
        self::assertFalse((new XmlRecords())->close('row', '<row>'));
    }

    public function testValuePublishesAnExplicitNullAndCaptureKeepsInnerPrecedence(): void
    {
        $records = new XmlRecords();
        $offset = 0;
        $records->open('<row b="parent">', '', $offset, '<row>', false);
        $records->open('<b>', '', $offset, '<row>', false);
        $records->value(null);
        $records->capture();
        self::assertNull($records->snapshot['b']);
    }

    public function testCaptureDoesNotCarryPreviousFileStateIntoANewRead(): void
    {
        $records = new XmlRecords();
        $records->read('<row a="1"/>');
        self::assertSame([[]], $records->read('<row/>'));
    }
}
