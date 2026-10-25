<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\Insert\Publication;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;

#[CoversClass(Publication::class)]
#[Medium]
final class PublicationTest extends TestCase
{
    public function testSupportsDoesNotPublishReplaceAsAnInsert(): void
    {
        $target = new WriteTarget(new QualifiedName(new Name('t')));
        self::assertFalse((new Publication())->supports(new InsertRows(new InsertInto(true, null, false, $target), [new InsertedRow([new StringLiteral(['42'])])])));
    }

    public function testPublishPlacesEachLocationOnItsSqlElement(): void
    {
        $sql = "INSERT   INTO t (amount) VALUE ('42')";
        $insert = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(InsertStatement::class, $insert);
        self::assertNotNull($insert->source);
        self::assertNotNull($insert->target->source);
        self::assertNotNull($insert->target->columns);
        self::assertArrayHasKey(0, $insert->target->columns);
        self::assertNotNull($insert->target->columns[0]->source);
        self::assertNotNull($insert->values->source);
        $source = $insert->values->rows[0][0]->source();
        self::assertNotNull($source);
        self::assertSame($sql, substr($sql, $insert->source->offset, $insert->source->length));
        self::assertSame('t', substr($sql, $insert->target->source->offset, $insert->target->source->length));
        self::assertSame('amount', substr($sql, $insert->target->columns[0]->source->offset, $insert->target->columns[0]->source->length));
        self::assertSame("VALUE ('42')", substr($sql, $insert->values->source->offset, $insert->values->source->length));
        self::assertSame("'42'", substr($sql, $source->offset, $source->length));
    }

    public function testSpanCopiesOnlyTheRecordedByteRange(): void
    {
        $value = new StringLiteral(['42']);
        self::assertNull((new Publication())->span($value, new SourceMap()));
        $span = (new Publication())->span($value, new SourceMap([new Origin($value, 12, 4)]));
        self::assertNotNull($span);
        self::assertSame(['offset' => 12, 'length' => 4], get_object_vars($span));
    }
}
