<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Script;

#[CoversNothing]
#[Small]
final class StatementTest extends TestCase
{
    public function testDialectStatementsExcludeGenericScripts(): void
    {
        self::assertContains(Statement::class, class_implements(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class));
        self::assertNotContains(Statement::class, class_implements(Script::class));
    }
}
