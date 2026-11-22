<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Access\Privileges::class)]
#[Medium]
final class PrivilegesTest extends TestCase
{
    public function testStatementLowersEveryGroup(): void
    {
        self::assertSame('GRANT SELECT ON TABLE t TO u', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('GRANT SELECT ON t TO u')->toString());
    }

    public function testStatementRoutesARoleCommand(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('DROP ROLE r');
        $result = (new \SqlSemantics\Platform\PostgreSql\Lowering\Access\Privileges(new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves(), \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172)))->statement($tree->find('DropRoleStmt')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\DropRole::class, $result);
    }
}
