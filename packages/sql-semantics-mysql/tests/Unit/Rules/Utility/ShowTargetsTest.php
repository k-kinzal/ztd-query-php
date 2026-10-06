<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;

#[CoversClass(ShowTargets::class)]
#[Medium]
final class ShowTargetsTest extends TestCase
{
    public function testDeriveResolvesTheTableWithItsColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $show = $semantics->analyze('SHOW COLUMNS FROM t', [$table]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns::class, $show->statement);
        self::assertCount(2, $show->facts->relation($show->statement->table)->shape->slots);
        $missing = $semantics->analyze('SHOW COLUMNS FROM t FROM other', [$table]);
        self::assertSame('Relation t does not exist.', $missing->facts->diagnostics[0]->message());
    }
}
