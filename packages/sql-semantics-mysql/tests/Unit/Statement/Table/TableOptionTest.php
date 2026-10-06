<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

#[CoversNothing]
#[Small]
final class TableOptionTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(TableOption::class, class_implements(EngineOption::class));
    }
}
