<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOption::class)]
#[Medium]
final class DatabaseOptionTest extends TestCase
{
    public function testOptionOfAnIdentifier(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d is_template = true')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\CreateDatabase::class, $statement);
        self::assertSame('is_template', $statement->options[0]->option());
    }

    public function testOptionOfAKeywordSpelling(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d CONNECTION LIMIT 3')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\CreateDatabase::class, $statement);
        self::assertSame('connection_limit', $statement->options[0]->option());
    }

    public function testRenderKeepsTheKeywordSpelling(): void
    {
        self::assertSame('CREATE DATABASE d WITH CONNECTION LIMIT = - 1 LOCATION = \'x\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d CONNECTION LIMIT -1 LOCATION \'x\'')->toString());
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d WITH strategy = wal_log')->facts->diagnostics);
    }

    public function testRenderKeepsAnIdentifierSpelledLikeAKeywordQuoted(): void
    {
        self::assertSame('CREATE DATABASE d WITH "owner" = x "template" = y OWNER = z', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d "owner" x "template" = y OWNER z')->toString());
    }
}
