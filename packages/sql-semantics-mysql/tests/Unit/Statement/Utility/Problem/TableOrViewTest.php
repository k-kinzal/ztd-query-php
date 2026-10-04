<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TableOrView::class)]
#[Small]
final class TableOrViewTest extends TestCase
{
    public function testDescribeNamesTheRelation(): void
    {
        self::assertSame('whether db.t is a base table or a view', (new TableOrView(new QualifiedName(new Name('t'), new Name('db'))))->describe());
    }
}
