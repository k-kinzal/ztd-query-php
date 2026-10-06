<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\CreateTableRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;

#[CoversClass(CreateTableRule::class)]
#[Medium]
final class CreateTableRuleTest extends TestCase
{
    public function testLegacyLowersTheLikeForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (LIKE s)')->find('create')[0];

        self::assertInstanceOf(CreateTableLike::class, (new CreateTableRule($lowering))->legacy($lowering->form($node)));
    }

    public function testEnclosedLowersAColumnList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) ENGINE = x')->find('create2a')[0];
        $create = (new CreateTableRule($lowering))->enclosed(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $lowering->form($node), 0, false);

        self::assertCount(1, $create->elements);
        self::assertCount(1, $create->options);
    }

    public function testPartitionedBuildsTheQueryForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (SELECT 1 AS a)')->find('create2a')[0];
        $create = (new CreateTableRule($lowering))->enclosed(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $lowering->form($node), 2, true);

        self::assertNotNull($create->query);
        self::assertSame(2, $create->temporaryWords);
    }

    public function testTemporaryCountsTheTemporaryWords(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TEMPORARY TEMPORARY TABLE t (a INT)')->find('opt_table_options')[0];

        self::assertSame(2, (new CreateTableRule($lowering))->temporary($node));
    }

    public function testLegacyQueryLowersTheQueryPart(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t IGNORE SELECT 1 AS a')->find('create3')[0];

        self::assertNotNull((new CreateTableRule($lowering))->legacyQuery($node));
    }

    public function testModernLowersCreateTable(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TEMPORARY TABLE t (a INT)')->find('create_table_stmt')[0];
        $create = (new CreateTableRule($lowering))->modern($node);

        self::assertInstanceOf(CreateTable::class, $create);
        self::assertSame(1, $create->temporaryWords);
    }

    public function testRestLowersOptionsPartitioningAndQuery(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t ENGINE x AS SELECT 1 AS a')->find('opt_create_table_options_etc')[0];
        $create = (new CreateTableRule($lowering))->rest(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], $node, 0, false);

        self::assertCount(1, $create->options);
        self::assertNotNull($create->query);
    }

    public function testQueryLowersTheQueryPart(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t REPLACE SELECT 1 AS a')->find('opt_duplicate_as_qe')[0];

        self::assertNotNull((new CreateTableRule($lowering))->query($node));
    }
}
