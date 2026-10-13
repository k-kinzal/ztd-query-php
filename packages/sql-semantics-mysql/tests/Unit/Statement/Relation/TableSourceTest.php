<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Statement\Relation;

#[CoversNothing]
#[Small]
final class TableSourceTest extends TestCase
{
    public function testTableInputsExcludeResultAndSpecialRelations(): void
    {
        self::assertContains(TableSource::class, class_implements(TableReference::class));
        self::assertContains(Relation::class, class_implements(ShowColumns::class));
        self::assertNotContains(TableSource::class, class_implements(ShowColumns::class));
        self::assertNotContains(TableSource::class, class_implements(Dual::class));
    }
}
