<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;

#[CoversNothing]
#[Small]
final class IndexOptionTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(IndexOption::class, class_implements(IndexComment::class));
    }
}
