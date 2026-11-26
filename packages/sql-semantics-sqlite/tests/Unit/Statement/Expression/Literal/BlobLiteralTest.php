<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(BlobLiteral::class)]
#[Medium]
final class BlobLiteralTest extends TestCase
{
    public function testAcceptsAnEmptyBlob(): void
    {
        self::assertSame('', (new BlobLiteral(''))->hex);
    }

    public function testDeriveScalarGivesBlob(): void
    {
        $operation = (new Inspection())->inspect("SELECT x'0aff'", []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(BlobLiteral::class, $statement->columns[0]->expression);
        self::assertSame('0AFF', $statement->columns[0]->expression->hex);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertEquals(new Known(Storage::Blob), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheDigitsInUpperCase(): void
    {
        self::assertSame('SELECT x\'0AFF\' AS c1, x\'\' AS c2', (new Inspection())->inspect('select X\'0aFf\' AS c1, x\'\' AS c2')->toString());
    }

    public function testRenderWritesANewlyBuiltBlob(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new BlobLiteral('00FF')), new ResultColumn(new BlobLiteral(''))]));

        self::assertSame("SELECT x'00FF', x''", $operation->toString());
    }
}
