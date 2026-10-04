<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::class)]
#[Small]
final class IntoPersistenceTest extends TestCase
{
    public function testTemporaryTellsTemporaryTables(): void
    {
        self::assertSame([false, true, false], [\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::Permanent->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::LocalTemp->temporary(), \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::Unlogged->temporary()]);
    }

    public function testKeywordsSplitTheSpelling(): void
    {
        self::assertSame([[], ['LOCAL', 'TEMPORARY']], [\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::Permanent->keywords(), \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::LocalTemporary->keywords()]);
    }
}
