<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Conditions;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Conditions::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    public function testProblemKeepsInvocationTimeConditionsSeparate(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])));
        self::assertNull((new Conditions())->problem($column));
    }

    public function testDeriveReportsAnAttributeAGeneratedColumnMayNotCarry(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\GeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Literal\NumberLiteral('1'), attributes: [new M\Table\Column\KeywordAttribute(M\Table\Column\Kind\ColumnKeyword::AutoIncrement)]));
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile(GrammarRelease::MySql847), [new Name('test')]));
        (new Conditions())->derive($column, $derivation, $derivation->environment());
        $information = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
        self::assertSame('AUTO_INCREMENT', $information->diagnostics[0]->attribute);
        self::assertSame($information->diagnostics[0], $information->warnings[0]->problem);
    }
}
