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
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\DefineRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateEnum;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DefineRule::class)]
#[Small]
final class DefineRuleTest extends TestCase
{
    public function testDefineLowersAggregatesAndOperators(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE AGGREGATE a(int) (sfunc = f, stype = int); CREATE OPERATOR s.=== (function = f)');
        $rule = new DefineRule($lowering);
        $aggregate = $rule->define($tree->find('DefineStmt')[0]);
        $operator = $rule->define($tree->find('DefineStmt')[1]);
        self::assertInstanceOf(Define::class, $aggregate);
        self::assertInstanceOf(Define::class, $operator);
        self::assertSame([ObjectKind::Aggregate, ObjectKind::Operator], [$aggregate->kind, $operator->kind]);
    }

    public function testTypeLowersAnEnum(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE TYPE mood AS ENUM ('sad', 'ok')");
        self::assertInstanceOf(CreateEnum::class, (new DefineRule($lowering))->type($tree->find('DefineStmt')[0]));
    }

    public function testOldLowersEveryAttribute(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE AGGREGATE a (basetype = int, sfunc = f, stype = int)');
        self::assertCount(3, (new DefineRule($lowering))->old($tree->find('old_aggr_definition')[0]));
    }

    public function testAttributesReadsTheAttributesOfTheCommand(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR === (function = f, colour = red)');
        $attributes = (new DefineRule($lowering))->attributes(ObjectKind::Operator, $tree->find('definition')[0]);
        self::assertSame([OperatorAttribute::Function, null], [$attributes[0]->known, $attributes[1]->known]);
    }

    public function testReadBuildsAnAttributeForEachElement(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $attributes = (new DefineRule($lowering))->read(RangeAttribute::class, [[new Name('subtype'), new StringConstant('int4')], [new Name('foo'), null]]);
        self::assertSame([RangeAttribute::Subtype, null], [$attributes[0]->known, $attributes[1]->known]);
    }
}
