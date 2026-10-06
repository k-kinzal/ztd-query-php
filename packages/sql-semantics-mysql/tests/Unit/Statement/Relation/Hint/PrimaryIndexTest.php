<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;

#[CoversClass(PrimaryIndex::class)]
#[Medium]
final class PrimaryIndexTest extends TestCase
{
    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('SELECT a FROM t USE INDEX (PRIMARY, i)', (new Semantics(Dialect::MySql))->analyze('select a from t use key (primary, i)')->toString());
    }
}
