<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\ChecksumColumn;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\Maintenance\MySql\StatusColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResultColumns::class)]
#[Medium]
final class ResultColumnsTest extends TestCase
{
    public function testStatusAttachesTheResultToItsProducingRequest(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CHECK TABLE t')->origin;
        $outputs = ResultColumns::status($origin);
        self::assertSame([0, 1, 2, 3], array_column($outputs, 'ordinal'));
        self::assertInstanceOf(StatusColumn::class, $outputs[3]->expression);
        self::assertSame($origin->scopeId, $outputs[3]->expression->scopeId);
        self::assertSame('Msg_text', $outputs[3]->name);
    }

    public function testChecksumIncludesNullableUnsignedChecksumFacts(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CHECKSUM TABLE t')->origin;
        $outputs = ResultColumns::checksum($origin);
        self::assertSame([0, 1], array_column($outputs, 'ordinal'));
        self::assertInstanceOf(ChecksumColumn::class, $outputs[1]->expression);
        self::assertSame('bigint unsigned', $outputs[1]->expression->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $outputs[1]->expression->nullability);
    }
}
