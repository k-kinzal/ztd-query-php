<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\View\SyntaxViews;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause;

#[CoversClass(SyntaxViews::class)]
#[Medium]
final class SyntaxViewsTest extends TestCase
{
    public function testStartingRefusesTheHeaderBeforeAnIncompleteBody(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql910);
        $sql = 'CREATE OR REPLACE VIEW IF NOT EXISTS v AS SELECT BINARY (';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-9.1.0'), $sql, strlen($sql), $profile);
        self::assertEquals([[38, new ParseFailure(new RefusedExistenceClause(23), true)]], $notices);
    }

    public function testRejectedKeepsTheCreateCommandDistinctFromAlter(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql910);
        $parser = new MySqlParser('mysql-9.1.0');
        $warnings = new SyntaxWarnings();
        self::assertNull($warnings->rejected($parser->parse('CREATE VIEW IF NOT EXISTS v AS SELECT 1'), $profile));
        $failure = $warnings->rejected($parser->parse('ALTER VIEW IF NOT EXISTS v AS SELECT ?'), $profile);
        self::assertEquals(new RefusedExistenceClause(11), $failure?->problem);
        self::assertSame(26, $failure?->end);
        self::assertTrue($failure->aborts);
    }

    public function testTailDoesNotRefuseAnOrdinaryAlterDefinition(): void
    {
        $platform = new Platform();
        $profile = new LanguageProfile(GrammarRelease::MySql910);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tail = $platform->parser($profile)->parse('ALTER VIEW v AS SELECT 1')->find('view_tail')[0];
        self::assertNull((new SyntaxViews())->tail($tail, $lowering));
    }

    public function testNamedRecognizesTheViewRoleRatherThanOtherExistenceClauses(): void
    {
        $parser = new MySqlParser('mysql-9.1.0');
        $table = $parser->parse('CREATE TABLE IF NOT EXISTS t(a INT)');
        self::assertNull((new SyntaxViews())->named($table->find('table_ident')[0], $table->tokens()));
        $view = $parser->parse('ALTER VIEW IF NOT EXISTS v AS SELECT 1');
        self::assertEquals(new RefusedExistenceClause(11), (new SyntaxViews())->named($view->find('table_ident')[0], $view->tokens())?->problem);
    }
}
