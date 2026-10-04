<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(KeyRule::class)]
#[Medium]
final class KeyRuleTest extends TestCase
{
    public function testElementLowersAForeignKey(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CONSTRAINT f FOREIGN KEY (a) REFERENCES p (x))')->find('key_def')[0];

        self::assertInstanceOf(ForeignKey::class, (new KeyRule($lowering))->element($node));
    }

    public function testIndexLowersAnIndexWithoutConstraint(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, FULLTEXT KEY f (a))')->find('key_def')[0];
        $index = (new KeyRule($lowering))->index($lowering->form($node));

        self::assertSame(IndexKind::FullText, $index->kind);
        self::assertTrue($index->keyword);
    }

    public function testConstrainedLowersAPrimaryKey(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CONSTRAINT c PRIMARY KEY (a))')->find('table_constraint_def')[0];
        $index = (new KeyRule($lowering))->constrained($lowering->form($node), null, null, null, 4, 6);

        self::assertSame(IndexKind::Primary, $index->kind);
    }

    public function testConstraintLowersTheConstraintClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0))')->find('opt_constraint_name')[0];

        self::assertSame('c', (new KeyRule($lowering))->constraint($node)?->name?->column->value);
    }

    public function testCheckLowersTheCondition(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CHECK (a > 0))')->find('check_constraint')[0];

        self::assertInstanceOf(Comparison::class, (new KeyRule($lowering))->check($node));
    }

    public function testOptionalCheckLowersALegacyColumnCheck(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT CHECK (a > 0))')->find('opt_check_constraint')[0];

        self::assertInstanceOf(CheckConstraint::class, (new KeyRule($lowering))->optionalCheck($node));
    }

    public function testEnforcedTellsWhetherNotIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CHECK (a > 0) NOT ENFORCED)')->find('constraint_enforcement')[0];

        self::assertFalse((new KeyRule($lowering))->enforced($node));
    }

    public function testEnforcementLowersAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, CHECK (a > 0))')->find('opt_constraint_enforcement')[0];

        self::assertNull((new KeyRule($lowering))->enforcement($node));
    }
}
