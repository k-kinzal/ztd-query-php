<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope;

#[CoversClass(IndexHint::class)]
#[Medium]
final class IndexHintTest extends TestCase
{
    public function testRenderWritesTheHints(): void
    {
        self::assertSame('SELECT a FROM t USE INDEX () FORCE INDEX FOR JOIN (i) IGNORE INDEX FOR ORDER BY (j, k)', (new Semantics(Dialect::MySql))->analyze('select a from t use index () force key for join (i) ignore index for order by (j, k)')->toString());
        self::assertSame('SELECT a FROM t x USE INDEX FOR GROUP BY (i)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t x use index for group by (i)')->toString());
    }

    public function testForceWithoutIndexIsRejected(): void
    {
        $this->expectExceptionMessage('FORCE INDEX and IGNORE INDEX name at least one index.');

        new IndexHint(IndexHintAction::Ignore, IndexHintScope::Join, []);
    }

    public function testAnotherIndexValueIsRejected(): void
    {
        $this->expectExceptionMessage('An index is named or is the primary key.');

        new IndexHint(IndexHintAction::Use, null, [new Dual()]);
    }
}
