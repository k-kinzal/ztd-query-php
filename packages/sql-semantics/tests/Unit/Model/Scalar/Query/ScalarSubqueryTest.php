<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;

#[CoversClass(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ScalarSubqueryTest extends TestCase
{
    public function testRetainsTheQueryAndItsResultTypeWithoutExecutingIt(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        self::assertSame('1', $value->query->resultColumns()[0]->expression->spelling());
        self::assertSame(BuiltinIdentity::Integer, $value->type->identity);
    }
}
