<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger;

#[CoversClass(ShowCreateTrigger::class)]
#[Medium]
final class ShowCreateTriggerTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TRIGGER db.x');
        self::assertInstanceOf(ShowCreateTrigger::class, $show->statement);
        self::assertSame('Trigger', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE TRIGGER db.x', (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TRIGGER db.x')->toString());
    }

    public function testNameRejectsACatalogQualifier(): void
    {
        $this->expectExceptionMessage('A stored-program name has at most a database qualifier.');
        new ShowCreateTrigger(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'), new \SqlSemantics\Statement\Identifier\Name('db'), new \SqlSemantics\Statement\Identifier\Name('catalog')));
    }

}
