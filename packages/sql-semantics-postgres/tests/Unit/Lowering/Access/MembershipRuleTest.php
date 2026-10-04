<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\MembershipRule::class)]
#[Medium]
final class MembershipRuleTest extends TestCase
{
    public function testStatementLowersARoleGrant(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT a TO b WITH ADMIN OPTION');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\MembershipRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('GrantRoleStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole::class, $result);
        self::assertCount(1, $result->options);
    }

    public function testStatementLowersARoleRevoke(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('REVOKE set OPTION FOR a FROM b RESTRICT');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\MembershipRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('RevokeRoleStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\RevokeRole::class, $result);
        self::assertSame('set', $result->option?->value);
    }

    public function testOptionsLowersTheValues(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('GRANT a TO b WITH ADMIN OPTION, INHERIT FALSE, SET TRUE');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\MembershipRule(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172)))->options($tree->find('grant_role_opt_list')[0]);
        self::assertSame(['OPTION', 'FALSE', 'TRUE'], [$result[0]->setting->value, $result[1]->setting->value, $result[2]->setting->value]);
    }
}
