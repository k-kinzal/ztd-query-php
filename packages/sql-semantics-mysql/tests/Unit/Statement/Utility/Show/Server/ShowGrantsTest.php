<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;

#[CoversClass(ShowGrants::class)]
#[Medium]
final class ShowGrantsTest extends TestCase
{
    public function testDeriveStatementDependsOnTheCurrentUser(): void
    {
        $show = (new Semantics())->inspect('SHOW GRANTS');
        self::assertInstanceOf(ShowGrants::class, $show->statement);
        self::assertFalse($show->shape()?->complete());
        $named = (new Semantics('mysql-5.6.51'))->inspect('SHOW GRANTS FOR u');
        self::assertSame('Grants for u@%', $named->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW GRANTS', (new Semantics())->inspect('SHOW GRANTS')->toString());
    }

    public function testRefusesUsingWithoutAnAccount(): void
    {
        $this->expectExceptionMessage('USING requires FOR.');
        new ShowGrants(null, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
    }
}
