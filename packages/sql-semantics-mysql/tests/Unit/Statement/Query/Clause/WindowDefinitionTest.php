<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(WindowDefinition::class)]
#[Medium]
final class WindowDefinitionTest extends TestCase
{
    public function testRenderWritesTheNameAndTheWindow(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a from t window w as (), v as (w)');

        self::assertSame('SELECT a FROM t WINDOW w AS (), v AS (w)', $operation->toString());
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('v', $operation->statement->windows[1]->name->value);
    }

    public function testANameDefinedTwiceIsReported(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WINDOW w AS (), W AS ()');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::DuplicateWindow, $operation->facts->diagnostics[0]->rule);
    }
}
