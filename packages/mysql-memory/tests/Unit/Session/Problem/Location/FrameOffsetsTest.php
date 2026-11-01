<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Session\Problem\Location\FrameOffsets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(FrameOffsets::class)]
#[Small]
final class FrameOffsetsTest extends TestCase
{
    public function testNodesRetainsTheIdentitiesOfTheCompleteIntervalOperand(): void
    {
        $first = new M\Literal\NumberLiteral('1');
        $second = new M\Literal\NumberLiteral('2');
        $row = new M\Expression\Row([$first, $second]);
        $bound = new M\Routine\Program\Selection\Frame\DeferredFrameBound(M\Call\Window\FrameBoundKind::Preceding, $row, M\Expression\IntervalUnit::Day);
        $frame = new M\Routine\Program\Selection\Frame\DeferredFrame(M\Call\Window\FrameUnit::Range, $bound);
        self::assertSame([spl_object_id($row) => true, spl_object_id($first) => true, spl_object_id($second) => true], (new FrameOffsets())->nodes($frame));
    }

    public function testConditionsKeepsMissingTableOpeningBeforeFrameChecks(): void
    {
        $column = new M\Name\ColumnUse(new Name('missing'));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableReference(new QualifiedName(new Name('absent'))));
        $row = new M\Expression\Row([$column, new M\Expression\Subquery\ScalarSubquery($query)]);
        $bound = new M\Routine\Program\Selection\Frame\DeferredFrameBound(M\Call\Window\FrameBoundKind::Preceding, $row, M\Expression\IntervalUnit::Day);
        $window = new M\Routine\Program\Selection\DeferredWindowSpec(frame: new M\Routine\Program\Selection\Frame\DeferredFrame(M\Call\Window\FrameUnit::Rows, $bound));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([], true));
        $window->deriveWindow($derivation, $derivation->environment());
        $facts = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
        $deferred = (new FrameOffsets())->conditions($window, $facts);
        $resolution = $column->scalarInformation()->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\MissingColumn::class, $resolution);
        self::assertSame([spl_object_id($resolution) => true], $deferred);
        self::assertCount(2, $facts->diagnostics);
    }
}
