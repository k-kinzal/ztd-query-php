<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ResolvedColumn::class)]
#[Medium]
final class ResolvedColumnTest extends TestCase
{
    public function testDeclarationReachesTheSuppliedColumnThroughTheOccurrence(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM t', [$table]);

        $resolution = $query->field('a')->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->inputRelation(), $resolution->relation);
        self::assertSame($table->declarations()[0]->columns[0], $resolution->declaration());
        self::assertSame($query->facts->relation($query->singleNamedInput())->shape->slots[0], $resolution->slot);
        self::assertSame(0, $resolution->depth);
    }

    public function testDeclarationIsNullForASlotOfADerivedQuery(): void
    {
        $query = (new Inspection())->inspect('SELECT x FROM (SELECT 1 AS x)');

        $resolution = $query->field('x')->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertNull($resolution->declaration());
    }

    public function testDeclarationIsNotReachedForANegativeDepth(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);

        $this->expectExceptionMessage('A correlation depth is not negative.');

        new ResolvedColumn(new TableInput(new QualifiedName(new Name('t'))), $slot, -1);
    }
}
