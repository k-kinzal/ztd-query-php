<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguageExtension::class)]
#[Medium]
final class CreateLanguageExtensionTest extends TestCase
{
    public function testIfNotExistsFollowsOrReplace(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE LANGUAGE plperl')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguageExtension::class, $statement);
        self::assertSame(true, $statement->ifNotExists());
    }

    public function testRenderKeepsTrusted(): void
    {
        self::assertSame('CREATE TRUSTED LANGUAGE plperl', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRUSTED LANGUAGE plperl')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE LANGUAGE plperl')->facts->diagnostics);
    }
}
