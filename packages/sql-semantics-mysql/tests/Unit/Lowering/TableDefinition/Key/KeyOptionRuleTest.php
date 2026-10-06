<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyOptionRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;

#[CoversClass(KeyOptionRule::class)]
#[Medium]
final class KeyOptionRuleTest extends TestCase
{
    public function testAlgorithmLowersTheStructureClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY USING RTREE (a))')->find('key_alg')[0];

        self::assertSame(IndexAlgorithm::Rtree, (new KeyOptionRule($lowering))->algorithm($node));
    }

    public function testMarkedConfirmsTheMarker(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, FULLTEXT (a))')->find('init_key_options')[0];

        self::assertNull((new KeyOptionRule($lowering))->marked($node, null));
    }

    public function testNameAndTypeLowersTheNameAndTheStructure(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY k TYPE HASH (a))')->find('opt_index_name_and_type')[0];
        [$name, $algorithm] = (new KeyOptionRule($lowering))->nameAndType($node);

        self::assertSame('k', $name?->column->value);
        self::assertSame(IndexAlgorithm::Hash, $algorithm);
    }

    public function testStructureLowersTheKeyword(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY USING BTREE (a))')->find('index_type')[0];

        self::assertSame(IndexAlgorithm::Btree, (new KeyOptionRule($lowering))->structure($node));
    }

    public function testOptionsLowersTheOptionList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY (a) COMMENT \'x\' INVISIBLE USING HASH)')->find('opt_index_options')[0];

        self::assertCount(3, (new KeyOptionRule($lowering))->options($node));
    }

    public function testOptionLowersOneOption(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a TEXT, FULLTEXT (a) WITH PARSER ngram)')->find('fulltext_key_opt')[0];

        self::assertInstanceOf(IndexParser::class, (new KeyOptionRule($lowering))->option($node));
    }

    public function testVisibleLowersVisibility(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY (a) INVISIBLE)')->find('visibility')[0];

        self::assertFalse((new KeyOptionRule($lowering))->visible($node));
    }
}
