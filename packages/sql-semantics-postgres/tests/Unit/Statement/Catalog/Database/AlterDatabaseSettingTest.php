<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabaseSetting::class)]
#[Small]
final class AlterDatabaseSettingTest extends TestCase
{
    public function testRenderWritesTheDatabaseBeforeTheSetting(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabaseSetting(new \SqlSemantics\Statement\Identifier\Name('d'), new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\RefreshDatabaseCollation(new \SqlSemantics\Statement\Identifier\Name('e'))))->render($out);
        self::assertSame('ALTER DATABASE d ALTER DATABASE e REFRESH COLLATION VERSION', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveStatementDerivesTheSetting(): void
    {
        $statement = new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabaseSetting(new \SqlSemantics\Statement\Identifier\Name('d'), new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabase(new \SqlSemantics\Statement\Identifier\Name('e'), [new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOption(new \SqlSemantics\Statement\Identifier\Name('foo'), \SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle::On)]));
        $derivation = new \SqlSemantics\Construction\Derivation(new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Statement\Identifier\Name('public')]));
        $statement->deriveStatement($derivation);
        self::assertCount(1, $derivation->facts()->diagnostics);
    }
}
