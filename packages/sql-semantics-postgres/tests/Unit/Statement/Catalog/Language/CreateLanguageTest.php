<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguage::class)]
#[Medium]
final class CreateLanguageTest extends TestCase
{
    public function testRenderDropsProcedural(): void
    {
        self::assertSame('CREATE OR REPLACE TRUSTED LANGUAGE l HANDLER h INLINE i VALIDATOR v.w', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE TRUSTED PROCEDURAL LANGUAGE l HANDLER h INLINE i VALIDATOR v.w')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE LANGUAGE l HANDLER h NO VALIDATOR')->facts->diagnostics);
    }

    public function testRejectsAHandlerClauseAsValidator(): void
    {
        $this->expectExceptionMessage('A language names a validator in its validator clause.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\CreateLanguage(false, false, new \SqlSemantics\Statement\Identifier\Name('l'), new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('h')]), null, new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Handler));
    }
}
