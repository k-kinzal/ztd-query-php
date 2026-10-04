<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial::class)]
#[Medium]
final class SetSpecialTest extends TestCase
{
    public function testDeriveStatementReportsSetCatalog(): void
    {
        self::assertSame('current database cannot be changed', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET CATALOG \'d\'')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementAcceptsSetSchema(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SCHEMA \'app\'')->facts->diagnostics);
    }

    public function testRenderWritesEachSetting(): void
    {
        self::assertSame(["SET SCHEMA 'a'", 'SET NAMES', 'SET NAMES DEFAULT', "SET LOCAL NAMES 'utf8'", 'SET ROLE none', "SET SESSION AUTHORIZATION 'u'", 'SET SESSION AUTHORIZATION DEFAULT', 'SET XML OPTION CONTENT', "SET TRANSACTION SNAPSHOT 's'"], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SCHEMA \'a\'')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET NAMES')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET NAMES DEFAULT')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET LOCAL NAMES \'utf8\'')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET ROLE NONE')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION AUTHORIZATION \'u\'')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION SESSION AUTHORIZATION DEFAULT')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET XML OPTION CONTENT')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TRANSACTION SNAPSHOT \'s\'')->toString()]);
    }

    public function testAValueMustFitTheSetting(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('The value does not fit the setting.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::XmlOption, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('x'));
    }

    public function testDefaultTakesNoValue(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('DEFAULT is written in place of a value.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Names, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('x'), true);
    }

    public function testDefaultIsRefusedWhereTheGrammarHasNone(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('The value does not fit the setting.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Role, null, true);
    }
}
