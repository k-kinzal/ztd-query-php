<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\ColumnRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;

#[CoversClass(ColumnRule::class)]
#[Medium]
final class ColumnRuleTest extends TestCase
{
    public function testSpecificationLowersAGeneratedColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1) STORED)')->find('field_def')[0];

        self::assertInstanceOf(GeneratedColumn::class, (new ColumnRule($lowering))->specification($node));
    }

    public function testSpecificationLowersALegacyTypeAndAttributes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL)')->find('type')[0];
        $specification = (new ColumnRule($lowering))->specification($node, $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL)')->find('opt_attribute')[0]);

        self::assertInstanceOf(OrdinaryColumn::class, $specification);
        self::assertCount(1, $specification->attributes);
    }

    public function testAttributesLowersTheListInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NULL DEFAULT 1 COMMENT \'c\')')->find('opt_column_attribute_list')[0];

        self::assertCount(3, (new ColumnRule($lowering))->attributes($node));
    }

    public function testSpecificationRefusesAnAttributeOfAGeneratedColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, g INT AS (a) NOT NULL DEFAULT 1)')->find('field_def')[1];

        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Incorrect usage of DEFAULT and generated column');

        (new ColumnRule($lowering))->specification($node);
    }

    public function testRefuseNamesTheFirstRefusedAttribute(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Incorrect usage of COLUMN_FORMAT and generated column');

        $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a) UNIQUE COLUMN_FORMAT FIXED ON UPDATE CURRENT_TIMESTAMP)');
    }

    public function testRefuseNamesTheTypeSerial(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Incorrect usage of SERIAL and generated column');

        (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD g SERIAL AS (1)');
    }

    public function testRefuseAcceptsTheOtherAttributes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, g INT AS (a) VIRTUAL NOT NULL UNIQUE KEY COMMENT \'c\' INVISIBLE)');

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testAlwaysAcceptsTheOptionalWords(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT GENERATED ALWAYS AS (1))')->find('opt_generated_always')[0];
        (new ColumnRule($lowering))->always($node);

        $this->expectExceptionMessage('No semantic rule is implemented for: opt_stored_attribute:');

        (new ColumnRule($lowering))->always($platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1))')->find('opt_stored_attribute')[0]);
    }

    public function testStorageLowersVirtualAndStored(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1) VIRTUAL)')->find('opt_stored_attribute')[0];

        self::assertSame(GeneratedStorage::Virtual, (new ColumnRule($lowering))->storage($node));
    }

    public function testExpressionLowersAGeneratedColumnFunction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1))')->find('generated_column_func')[0];

        self::assertInstanceOf(NumberLiteral::class, (new ColumnRule($lowering))->expression($node));
    }

    public function testParseLowersParseGcolExpr(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('PARSE_GCOL_EXPR(1)')->find('parse_gcol_expr')[0];

        self::assertInstanceOf(NumberLiteral::class, (new ColumnRule($lowering))->parse($node)->expression);
    }
}
