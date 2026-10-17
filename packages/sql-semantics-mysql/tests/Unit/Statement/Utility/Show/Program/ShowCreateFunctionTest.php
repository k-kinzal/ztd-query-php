<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction;

#[CoversClass(ShowCreateFunction::class)]
#[Medium]
final class ShowCreateFunctionTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE FUNCTION db.x');
        self::assertInstanceOf(ShowCreateFunction::class, $show->statement);
        self::assertSame('Function', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE FUNCTION db.x', (new Semantics(Dialect::MySql))->analyze('SHOW CREATE FUNCTION db.x')->toString());
    }

    public function testNameRejectsACatalogQualifier(): void
    {
        $this->expectExceptionMessage('A stored-program name has at most a database qualifier.');
        new ShowCreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'), new \SqlSemantics\Statement\Identifier\Name('db'), new \SqlSemantics\Statement\Identifier\Name('catalog')));
    }

}
