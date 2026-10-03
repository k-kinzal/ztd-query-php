<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Catalogs;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Catalogs::class)]
#[Small]
final class CatalogsTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE SCHEMA s');
        $this->expectExceptionMessage('No semantic rule is implemented for: CreateSchemaStmt: CREATE SCHEMA ColId OptSchemaEltList');
        $lowering->catalogs->statement($tree->find('CreateSchemaStmt')[0]);
    }

    public function testEnumValuesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE TYPE e AS ENUM ('a', 'b')");
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_enum_val_list: enum_val_list');
        $lowering->catalogs->enumValues($tree->find('opt_enum_val_list')[0]);
    }
}
