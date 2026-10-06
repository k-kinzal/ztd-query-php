<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Selection;

#[CoversClass(Selection::class)]
#[Medium]
final class SelectionTest extends TestCase
{
    public function testInputAnswersTheRelationStructureOrNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $withInput = $semantics->analyze('SELECT a FROM t')->statement;
        $withoutInput = $semantics->analyze('SELECT 1')->statement;

        self::assertInstanceOf(Selection::class, $withInput);
        self::assertInstanceOf(Selection::class, $withoutInput);
        self::assertInstanceOf(TableInput::class, $withInput->input());
        self::assertNull($withoutInput->input());
    }
}
