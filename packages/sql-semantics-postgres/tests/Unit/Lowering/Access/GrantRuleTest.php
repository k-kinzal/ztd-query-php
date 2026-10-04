<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule::class)]
#[Medium]
final class GrantRuleTest extends TestCase
{
    public function testStatementLowersGrant(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO a');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('GrantStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant::class, $result);
    }

    public function testStatementLowersRevokeOfTheGrantOption(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('REVOKE GRANT OPTION FOR SELECT ON t FROM a CASCADE');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('RevokeStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke::class, $result);
        self::assertTrue($result->grantOptionOnly);
    }

    public function testTargetLowersRelationsWithoutTheNoiseWord(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON a, s.b TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->target($tree->find('privilege_target')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget::class, $result);
        self::assertSame('s', $result->relations[1]->name->schema?->value);
    }

    public function testTargetLowersSchemaContents(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON ALL SEQUENCES IN SCHEMA a TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->target($tree->find('privilege_target')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget::class, $result);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Sequence, $result->object());
    }

    public function testTargetLowersLargeObjects(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON LARGE OBJECT 5 TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->target($tree->find('privilege_target')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget::class, $result);
    }

    public function testTargetLowersEveryNamedKind(): void
    {
        self::assertSame('GRANT ALL ON TABLESPACE a TO u', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT ALL ON TABLESPACE a TO u; ')->toString());
    }

    public function testParametersLowersDottedNames(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT SET ON PARAMETER a.b, c TO u');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\GrantRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->parameters($tree->find('parameter_name_list')[0]);
        self::assertSame(['a.b', 'c'], [$result[0]->parameter(), $result[1]->parameter()]);
    }
}
