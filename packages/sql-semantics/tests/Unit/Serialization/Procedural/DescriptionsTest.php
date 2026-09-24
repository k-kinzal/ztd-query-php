<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Descriptions;

#[CoversClass(Descriptions::class)]
#[Medium]
final class DescriptionsTest extends TestCase
{
    public function testWriteNormalizesTheCommandSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DESC t id');
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertSame("DESCRIBE `t` 'id'", Descriptions::write($statement)->toString());
    }

    public function testBytesChoosesHexadecimalForUnprintableBytes(): void
    {
        self::assertSame("X'00ff'", Descriptions::bytes("\x00\xff")->toString());
        self::assertSame("'a%'", Descriptions::bytes('a%')->toString());
    }
}
