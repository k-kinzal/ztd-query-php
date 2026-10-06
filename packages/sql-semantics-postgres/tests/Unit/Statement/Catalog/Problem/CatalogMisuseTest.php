<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse::class)]
#[Medium]
final class CatalogMisuseTest extends TestCase
{
    public function testMessageNamesTheSubject(): void
    {
        self::assertSame('unacceptable schema name "pg_app"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA pg_app')->facts->diagnostics[0]->message());
    }

    public function testMessageWithoutSubject(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d OWNER a OWNER b')->facts->diagnostics[0]->message());
    }

    public function testRejectsAMissingSubject(): void
    {
        $this->expectExceptionMessage('A catalog problem names one subject for each place of its message.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule::UnknownOption);
    }
}
