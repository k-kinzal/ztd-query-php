<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlField::class)]
final class PostgreSqlFieldTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFields')]
    public function testFieldsRoundTrip(PostgreSqlField $field): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT EXTRACT(' . $field->value . ' FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(Extract::class, $extract);
        self::assertSame($field, $extract->field);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    /**
     * @return list<array{PostgreSqlField}>
     */
    public static function providerFields(): array
    {
        return array_map(static fn (PostgreSqlField $field): array => [$field], PostgreSqlField::cases());
    }
}
