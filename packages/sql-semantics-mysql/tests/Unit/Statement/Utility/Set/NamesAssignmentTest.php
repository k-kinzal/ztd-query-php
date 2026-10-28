<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NamesAssignment::class)]
#[Medium]
final class NamesAssignmentTest extends TestCase
{
    public function testDeriveItemRetainsTheEntireExpressionAndFollowingAssignments(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SET NAMES = (1, 2), @after = 3');
        self::assertInstanceOf(SetVariables::class, $statement);
        self::assertCount(2, $statement->items);
        self::assertInstanceOf(NamesAssignment::class, $statement->items[0]);
        self::assertInstanceOf(Row::class, $statement->items[0]->value);
        self::assertEquals(new RefusedNamesAssignment(10, 18), $statement->diagnostics()[0]);
        self::assertInstanceOf(ParseFailure::class, $statement->warnings()[0]);
        self::assertSame(NoticePhase::Contextualization, $statement->warnings()[0]->phase);
    }

    public function testDeriveItemKeepsTheExpressionConditionBeforeTheAssignment(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SET NAMES = @@names_missing');
        self::assertInstanceOf(UnknownSystemVariable::class, $statement->diagnostics()[0]);
        self::assertInstanceOf(RefusedNamesAssignment::class, $statement->diagnostics()[1]);
    }

    public function testDeriveItemRetainsAnUnreachedVariableOperandInLaterReleases(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SET NAMES = @@names_missing');
        self::assertInstanceOf(SetVariables::class, $statement);
        self::assertInstanceOf(NamesAssignment::class, $statement->items[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable::class, $statement->items[0]->value);
        self::assertCount(1, $statement->diagnostics());
        self::assertInstanceOf(RefusedNamesAssignment::class, $statement->diagnostics()[0]);
        self::assertInstanceOf(ParseFailure::class, $statement->warnings()[0]);
        self::assertInstanceOf(RefusedNamesAssignment::class, $statement->warnings()[0]->problem);
        self::assertInstanceOf(ParseFailure::class, $statement->warnings()[1]);
        self::assertInstanceOf(UnknownSystemVariable::class, $statement->warnings()[1]->problem);
    }

    public function testConditionPublishesLexicalShadowingWithoutInputText(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::MySql5651), [new Name('test')]);
        $derivation = new Derivation($context);
        (new NamesAssignment(new NumberLiteral('1')))->condition($derivation, true);
        self::assertEquals([new RefusedNamesAssignment(0, 0, true)], $derivation->facts()->diagnostics);
        self::assertInstanceOf(ParseFailure::class, $derivation->facts()->warnings[0]);
        self::assertSame(NoticePhase::Syntax, $derivation->facts()->warnings[0]->phase);
    }

    public function testConditionUsesTheStoredVariableScope(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE names INT; IF 0 THEN SET NAMES=(1,2)+1; END IF; END');
        self::assertInstanceOf(RefusedNamesAssignment::class, $statement->diagnostics()[0]);
        self::assertTrue($statement->diagnostics()[0]->shadowed);
    }

    public function testRenderWritesTheCompleteOperandAndLaterItems(): void
    {
        self::assertSame('SET @before = 0, NAMES = (1, 2) + 3, @after = 4', (new Semantics(Dialect::MySql))->analyze('SET @before=0, NAMES=(1,2)+3, @after=4')->toString());
    }
}
