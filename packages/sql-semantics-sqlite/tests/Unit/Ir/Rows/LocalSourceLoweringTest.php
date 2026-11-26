<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSourceLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSourceLowering
 */
final class LocalSourceLoweringTest extends TestCase
{
    public function testLowerBindsBothSpellingsToTheSameLocalSlot(): void
    {
        $semantics = new Semantics();
        $external = (new Inspection())->inspect('CREATE TABLE c (a, b)');
        $sql = 'WITH c(a, b) AS (VALUES (1, 9)) SELECT b AS first, v.b AS second FROM c AS v';
        $checked = (new Inspection())->inspect($sql, [$external]);
        self::assertInstanceOf(WithQuery::class, $checked->statement);
        $source = (new LocalSourceLowering())->lower($checked->binding(), $checked->statement);
        $query = $semantics->analyze($sql, $semantics->context(['CREATE TABLE c (a, b)']));

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(LocalSource::class, $query->source);
        self::assertEquals($source, $query->source);
        self::assertEquals(new ColumnReference(1), $query->columns[0]->expression);
        self::assertEquals($query->columns[0]->expression, $query->columns[1]->expression);
        self::assertSame('b', $query->source->columns[1]->value);
        self::assertInstanceOf(ValuesQuery::class, $query->source->query);
        $value = $query->source->query->rows[0]->values[1];
        self::assertInstanceOf(IntegerLiteral::class, $value);
        self::assertSame('9', $value->decimal);
    }
}
