<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\TableChangeRules;

#[CoversClass(TableChangeRules::class)]
#[Medium]
final class TableChangeRulesTest extends TestCase
{
    public function testIgnoredRecognizesTheCompletedAlterationTargetBeforeLaterSyntax(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-5.6.51');
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        $sql = 'ALTER IGNORE TABLE t ALTER a SET DEFAULT (1)';

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AlterIgnore], array_column((new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile), 1));
        self::assertSame([], (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, 'ALTER IGNORE TABLE', 18, $profile));
        self::assertSame([], (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, 'UPDATE IGNORE t SET a=', 21, $profile));
    }

    public function testCompletedRefusesAnUnknownModifierInAProgramBody(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('bad is not a LOCK the server knows.');
        (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t LOCK=bad');
    }

    public function testStatementLowersAStatementNode(): void
    {
        self::assertSame('TRUNCATE t', (new Semantics(Dialect::MySql))->analyze('TRUNCATE t')->toString());
    }

    public function testDefinitionLowersARoutedProduction(): void
    {
        self::assertSame('ALTER TABLE t FORCE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter table t force')->toString());
    }

    public function testPartitioningLowersTheClauseOfCreateTable(): void
    {
        self::assertSame('CREATE TABLE t (a INT) PARTITION BY KEY (a)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE TABLE t (a INT) PARTITION BY KEY (a)')->toString());
    }

    public function testAlterOptionsLowersTheOptionsOfDropIndex(): void
    {
        self::assertSame('DROP INDEX i ON t ALGORITHM = inplace', (new Semantics(Dialect::MySql))->analyze('drop index i on t algorithm = inplace')->toString());
    }

    public function testPartitionNamesLowersAllOrNames(): void
    {
        self::assertSame('ALTER TABLE t ANALYZE PARTITION p, q', (new Semantics(Dialect::MySql))->analyze('alter table t analyze partition p, q')->toString());
    }
}
