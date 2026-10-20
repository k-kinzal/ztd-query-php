<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\HandlerHistory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Verifies that handler witnesses establish data without replacing read operands.
 */
#[CoversNothing]
#[Small]
final class HandlerHistoryTest extends TestCase
{
    /**
     * Retains the handle identity and populates enough key columns for a composite seek.
     */
    public function testStepsProvideRowsAndTheOriginalHandle(): void
    {
        $name = new Name("cursor ' あ");
        $read = new M\Dml\Handler\HandlerIndexSeek(new M\Dml\Handler\OpenHandler($name), new Name('index_b'), M\Dml\Handler\KeyComparison::Equal, [new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $steps = (new HandlerHistory())->steps($read, 1109);
        self::assertCount(4, $steps);
        self::assertInstanceOf(M\Dml\Handler\HandlerOpen::class, $steps[3]);
        self::assertSame($name, $steps[3]->alias);
        self::assertInstanceOf(M\Dml\Insert\InsertRows::class, $steps[2]);
        self::assertCount(4, $steps[2]->rows);
        self::assertCount(2, $steps[2]->rows[0]->values);
        self::assertSame([], (new HandlerHistory())->steps($read, 1054));
        self::assertSame([], (new HandlerHistory())->steps(new M\Literal\NullLiteral(), 1109));
    }
}
