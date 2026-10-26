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
    public function testStatementsKeepParameterOccurrencesDistinct(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $select = $lowering->statements($platform->parser($profile)->parse('SELECT ?, ?'))[0];

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class, $select->items[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class, $select->items[1]->expression);
        self::assertNotSame($select->items[0]->expression, $select->items[1]->expression);
        self::assertSame('?', $select->items[0]->expression->marker);
        self::assertSame('?', $select->items[1]->expression->marker);
    }

    public function testStatementsAnswerTheOneStatementOfAnInput(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $parser = $platform->parser($profile);

        self::assertSame([], $lowering->statements($parser->parse('')));
        self::assertCount(1, $lowering->statements($parser->parse('SELECT 1')));
        self::assertCount(1, $lowering->statements($parser->parse('SELECT 1;')));
        $root = $lowering->statements($parser->parse('SELECT 1;'))[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $root);
        self::assertInstanceOf(Select::class, $root->query);
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
        $root = $lowering->statements($parser->parse('SELECT 1;'))[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $root);
        self::assertInstanceOf(Select::class, $root->query);
        $root = $lowering->statements($parser->parse('SELECT a FROM t WHERE a = 1'))[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $root);
        self::assertInstanceOf(Select::class, $root->query);
    }

    public function testStatementHandsARuleToTheFamilyThatOwnsIt(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SET ROLE NONE');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);

        self::assertInstanceOf(SetRole::class, $operation->statement);
        self::assertSame('SET ROLE NONE', $operation->toString());
    }

    public function testStatementHandsBeginToTheServerFamily(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('BEGIN');

        self::assertInstanceOf(Begin::class, $operation);
        self::assertSame('BEGIN', $operation->toString());
    }

    public function testDefinitionRoutesCreateAlterAndDrop(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP TABLE t');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\Alter\\', $operation->statement::class);
        self::assertSame('DROP TABLE t', $operation->toString());
    }

    public function testRoutedHandsAModernStatementToItsFamily(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('DROP TABLE t');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\Alter\\', $operation->statement::class);
        self::assertSame('DROP TABLE t', $operation->toString());
    }

    public function testRoutedHandsADefinitionToItsFamilyThroughTheRoutes(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');

        $tablespace = $semantics->analyze("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd'");
        $database = $semantics->analyze('CREATE DATABASE d');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('CREATE DATABASE d');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $tablespace);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $database);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $legacy);
        self::assertInstanceOf(CreateTablespace::class, $tablespace->statement);
        self::assertInstanceOf(CreateDatabase::class, $database->statement);
        self::assertInstanceOf(CreateDatabase::class, $legacy->statement);
    }

    public function testFormAnswersTheProductionOfANode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertSame('start_entry: sql_statement', $lowering->form($platform->parser($profile)->parse('SELECT 1'))->signature);
        self::assertSame($profile, $lowering->profile);
    }

    public function testHintsAnswerTheHintsOfTheCommentAfterAKeyword(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT /*+ QB_NAME(q) */ 1');

        $lowering->statements($tree);

        self::assertSame(['QB_NAME(`q`)'], array_map(static fn ($hint): string => $hint->text(), $lowering->hints($tree->tokens()[0])));
        self::assertSame([], $lowering->hints($tree->tokens()[1]));
    }
    public function testInProgramScopesTheDatabaseWithoutReplacingInputOrigins(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $client = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1');
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile, client: $client, canonicalNames: true);
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'), new \SqlSemantics\Statement\Identifier\Name('d'));
        $scoped = $lowering->inProgram($name);
        self::assertNull($lowering->programName);
        self::assertSame($name, $scoped->inProgram(null)->programName);
        self::assertSame($lowering->origins, $scoped->origins);
        self::assertSame($client, $scoped->client);
        self::assertNull($scoped->inputClient);
        self::assertSame($lowering->leaves, $scoped->leaves);
    }

    public function testUnpreparedSharesInputOwnershipWithoutNativeContextualization(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $client = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1');
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile, client: $client, canonicalNames: true);
        $inactive = $lowering->unprepared();
        self::assertFalse($inactive->contextualized);
        self::assertSame($lowering->leaves, $inactive->leaves);
        self::assertSame($lowering->origins, $inactive->origins);
        self::assertSame($client, $inactive->client);
        self::assertNull($inactive->inputClient);
        self::assertTrue($lowering->contextualized);
    }

    public function testUnpreparedRetainsHintsAndColumnOwnershipInDefinitions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT /*+ INDEX(t ka) */ a FROM t');
        $lowering->statements($tree);
        $lowering->columnTable = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
        $inactive = $lowering->unprepared();
        self::assertNotEmpty($lowering->hints($tree->tokens()[0]));
        self::assertSame($lowering->hints($tree->tokens()[0]), $inactive->hints($tree->tokens()[0]));
        self::assertSame($lowering->columnTable, $inactive->columnTable);
    }

}
