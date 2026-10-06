<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(FilterKind::class)]
#[Small]
final class FilterKindTest extends TestCase
{
    public function testMemberAnswersTheValueClassOfEachFilter(): void
    {
        self::assertSame(Name::class, FilterKind::IgnoreDb->member());
        self::assertSame(QualifiedName::class, FilterKind::DoTable->member());
        self::assertSame(Text::class, FilterKind::WildDoTable->member());
        self::assertSame(DatabaseRewrite::class, FilterKind::RewriteDb->member());
    }
}
