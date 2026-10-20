<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\Update\ClauseFacts;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(ClauseFacts::class)]
#[Small]
final class ClauseFactsTest extends TestCase
{
    public function testDerivePreservesACompleteRowPredicate(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([], true));
        (new ClauseFacts())->derive($row, $derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($row));
        self::assertSame([M\Expression\Problem\OperandColumns::class], array_map(static fn ($problem): string => $problem::class, $derivation->facts()->diagnostics));
    }
}
