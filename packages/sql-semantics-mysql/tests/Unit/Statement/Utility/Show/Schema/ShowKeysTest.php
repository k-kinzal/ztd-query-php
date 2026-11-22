<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowKeys::class)]
#[Medium]
final class ShowKeysTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableInTheWrittenDatabase(): void
    {
        $show = (new Inspection())->inspect('SHOW EXTENDED INDEX FROM t FROM db');
        self::assertInstanceOf(ShowKeys::class, $show->statement);
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE db.t (a INT)');
        $keys = $semantics->inspect('SHOW INDEX FROM other.t IN db', [$table]);
        self::assertInstanceOf(ShowKeys::class, $keys->statement);
        self::assertSame([], $keys->facts->diagnostics);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Inspection())->inspect('SHOW EXTENDED INDEX FROM t FROM db');
        self::assertInstanceOf(ShowKeys::class, $show->statement);
        self::assertSame('Visible', $show->facts->relation($show->statement)->shape->slots[13]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW EXTENDED INDEXES FROM t FROM db', (new Inspection())->inspect('SHOW EXTENDED INDEX FROM t FROM db')->toString());
    }
}
