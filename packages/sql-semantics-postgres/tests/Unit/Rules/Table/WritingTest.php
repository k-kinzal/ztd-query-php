<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Writing::class)]
#[Medium]
final class WritingTest extends TestCase
{
    public function testNamesWritesNamesSeparatedByCommas(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->names($out, [new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('B')]);
        self::assertSame('a , "B"', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testParenthesizedWritesNamesBetweenParentheses(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->parenthesized($out, [new \SqlSemantics\Statement\Identifier\Name('a')]);
        self::assertSame('( a )', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testConstraintNameWritesConstraintAndTheName(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->constraintName($out, new \SqlSemantics\Statement\Identifier\Name('c'));
        self::assertSame('CONSTRAINT c', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testDefinitionsWritesNothingForNoDefinition(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->definitions($out, []);
        self::assertSame('', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testSequenceWritesNodesOneAfterAnother(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->sequence($out, [\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::Deferrable, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NotValid]);
        self::assertSame('DEFERRABLE NOT VALID', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testNullTreatmentWritesNullsNotDistinct(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->nullTreatment($out, false);
        self::assertSame('NULLS NOT DISTINCT', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testIndexParametersWritesTheTablespace(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->indexParameters($out, [], new \SqlSemantics\Statement\Identifier\Name('s'));
        self::assertSame('USING INDEX TABLESPACE s', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testWithDataWritesWithNoData(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->withData($out, false);
        self::assertSame('WITH NO DATA', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }

    public function testSeparatedWritesTheKeywordBetweenNodes(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Writing())->separated($out, [\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::Deferrable, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NotValid], 'OR');
        self::assertSame('DEFERRABLE OR NOT VALID', implode(' ', array_map(static fn ($piece): string => $piece->text, $out->pieces())));
    }
}
