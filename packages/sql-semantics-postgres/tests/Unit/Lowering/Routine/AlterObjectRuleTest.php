<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\AlterObjectRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

#[CoversClass(AlterObjectRule::class)]
#[Small]
final class AlterObjectRuleTest extends TestCase
{
    public function testSchemaLowersIfExists(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER MATERIALIZED VIEW IF EXISTS v SET SCHEMA s');
        $move = (new AlterObjectRule($lowering))->schema($tree->find('AlterObjectSchemaStmt')[0]);
        self::assertSame([ObjectKind::MaterializedView, true, 's'], [$move->kind, $move->ifExists, $move->schema->value]);
    }

    public function testOwnerLowersTheRole(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER OPERATOR FAMILY f USING btree OWNER TO CURRENT_ROLE');
        self::assertSame(RoleSpecKind::CurrentRole, (new AlterObjectRule($lowering))->owner($tree->find('AlterOwnerStmt')[0])->owner->kind);
    }

    public function testDependsLowersNo(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER TRIGGER g ON t NO DEPENDS ON EXTENSION e');
        self::assertTrue((new AlterObjectRule($lowering))->depends($tree->find('AlterObjectDependsStmt')[0])->remove);
    }
}
