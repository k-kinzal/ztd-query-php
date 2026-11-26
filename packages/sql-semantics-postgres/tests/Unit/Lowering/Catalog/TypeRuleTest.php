<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TypeRule::class)]
#[Medium]
final class TypeRuleTest extends TestCase
{
    public function testStatementLowersAnEnumChange(): void
    {
        self::assertSame('ALTER TYPE t RENAME VALUE \'a\' TO \'b\'', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER TYPE t RENAME VALUE \'a\' TO \'b\'')->toString());
    }

    public function testChangesLowersEveryCommand(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves(), \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TYPE t ADD ATTRIBUTE a int4, DROP ATTRIBUTE IF EXISTS b, ALTER ATTRIBUTE c TYPE text');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TypeRule($lowering);
        self::assertCount(3, $rule->changes($tree->find('alter_type_cmds')[0]));
    }
}
