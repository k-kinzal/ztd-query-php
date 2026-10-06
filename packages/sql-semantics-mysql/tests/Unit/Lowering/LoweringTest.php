<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;

#[CoversClass(Lowering::class)]
#[Medium]
final class LoweringTest extends TestCase
{
    public function testStatementsAnswerTheOneStatementOfAnInput(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $parser = $platform->parser($profile);

        self::assertSame([], $lowering->statements($parser->parse('')));
        self::assertCount(1, $lowering->statements($parser->parse('SELECT 1')));
        self::assertCount(1, $lowering->statements($parser->parse('SELECT 1;')));
        self::assertInstanceOf(Select::class, $lowering->statements($parser->parse('SELECT 1;'))[0]);
        self::assertCount(3, $lowering->leaves->all());
        $recorded = new Leaves();
        $projected = (new Lowering($platform->productions($profile), $recorded, $profile))->statements($parser->parse('SELECT a, 1 FROM t'));
        self::assertCount(1, $projected);
        self::assertCount(4, $recorded->all());
    }

    public function testStatementsAnswerTheOneStatementOfALegacyInput(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $parser = $platform->parser($profile);

        self::assertSame([], $lowering->statements($parser->parse('')));
        self::assertInstanceOf(Select::class, $lowering->statements($parser->parse('SELECT 1;'))[0]);
        self::assertInstanceOf(Select::class, $lowering->statements($parser->parse('SELECT a FROM t WHERE a = 1'))[0]);
    }

    public function testStatementHandsARuleToTheFamilyThatOwnsIt(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SET ROLE NONE');

        self::assertInstanceOf(SetRole::class, $operation->statement);
        self::assertSame('SET ROLE NONE', $operation->toString());
    }

    public function testStatementHandsBeginToTheServerFamily(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('BEGIN');

        self::assertInstanceOf(Begin::class, $operation->statement);
        self::assertSame('BEGIN', $operation->toString());
    }

    public function testDefinitionRoutesCreateAlterAndDrop(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP TABLE t');

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\Alter\\', $operation->statement::class);
        self::assertSame('DROP TABLE t', $operation->toString());
    }

    public function testRoutedHandsAModernStatementToItsFamily(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('DROP TABLE t');

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\Alter\\', $operation->statement::class);
        self::assertSame('DROP TABLE t', $operation->toString());
    }

    public function testRoutedHandsADefinitionToItsFamilyThroughTheRoutes(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');

        self::assertInstanceOf(CreateTablespace::class, $semantics->analyze("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'")->statement);
        self::assertInstanceOf(CreateDatabase::class, $semantics->analyze('CREATE DATABASE d')->statement);
        self::assertInstanceOf(CreateDatabase::class, (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('CREATE DATABASE d')->statement);
    }

    public function testFormAnswersTheProductionOfANode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertSame('start_entry: sql_statement', $lowering->form($platform->parser($profile)->parse('SELECT 1'))->signature);
        self::assertSame($profile, $lowering->profile);
    }
}
