<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[CoversClass(QualifiedColumn::class)]
final class QualifiedColumnTest extends TestCase
{
    public function testQualified(): void
    {
        self::assertSame('`a``b`.`c``d`', (new QualifiedColumn(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter()))->qualified('a`b', 'c`d'));
    }

}
