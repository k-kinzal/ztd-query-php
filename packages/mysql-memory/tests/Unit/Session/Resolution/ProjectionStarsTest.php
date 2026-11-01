<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Session\Resolution\ProjectionStars;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectionStars::class)]
#[Small]
final class ProjectionStarsTest extends TestCase
{
    public function testLocatePositionsAnEmptyQualifierBeforeItsExpansion(): void
    {
        $star = new \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('')));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$star], new \SqlSemantics\Platform\MySql\Statement\Relation\Dual());
        [$wildcards, $stars] = (new ProjectionStars())->locate($query, [4]);

        self::assertSame([[$query, [4, 1, -2]]], $stars);
        self::assertSame([[$star, [4, 1, -1]]], $wildcards);
    }
}
