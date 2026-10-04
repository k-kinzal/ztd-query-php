<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier::class)]
#[Small]
final class SetQuantifierTest extends TestCase
{
    public function testQuantifierOfUnionAll(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION ALL SELECT 2');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier::All, $statement->quantifier);
    }
}
