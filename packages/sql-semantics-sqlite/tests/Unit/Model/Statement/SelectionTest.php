<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Selection;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Selection::class)]
#[Medium]
final class SelectionTest extends TestCase
{
    public function testInputAnswersTheRelationStructureOrNull(): void
    {
        $semantics = new Inspection();
        $withInput = $semantics->inspect('SELECT a FROM t')->statement;
        $withoutInput = $semantics->inspect('SELECT 1')->statement;

        self::assertInstanceOf(Selection::class, $withInput);
        self::assertInstanceOf(Selection::class, $withoutInput);
        self::assertInstanceOf(TableInput::class, $withInput->input());
        self::assertNull($withoutInput->input());
    }
}
