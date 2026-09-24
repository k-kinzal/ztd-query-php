<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(BuiltinCallModel::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class BuiltinCallModelTest extends TestCase
{
    /**
     * @param list<Domain> $arguments
     */
    #[DataProvider('providerEvaluate')]
    public function testEvaluateOfEveryModelledFunction(string $function, array $arguments, string $expected): void
    {
        self::assertSame($expected, (new BuiltinCallModel())->evaluate($function, $arguments)->patterns()[0]->display());
    }

    /**
     * @return list<array{string, list<Domain>, string}>
     */
    public static function providerEvaluate(): array
    {
        return [
            ['sprintf', [Domain::literal('a%s'), Domain::literal('b')], 'ab'],
            ['vsprintf', [Domain::literal('a%s'), Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('b'))]))], 'ab'],
            ['implode', [Domain::literal('-'), Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a')), new ArrayEntry(null, Domain::literal('b'))]))], 'a-b'],
            ['join', [Domain::literal('-'), Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('a'))]))], 'a'],
            ['str_repeat', [Domain::literal('?'), Domain::literal(3)], '???'],
            ['strtolower', [Domain::literal('AB')], 'ab'],
            ['strtoupper', [Domain::literal('ab')], 'AB'],
            ['ucfirst', [Domain::literal('ab')], 'Ab'],
            ['lcfirst', [Domain::literal('AB')], 'aB'],
            ['trim', [Domain::literal(' a ')], 'a'],
            ['ltrim', [Domain::literal(' a')], 'a'],
            ['rtrim', [Domain::literal('a ')], 'a'],
            ['str_replace', [Domain::literal('a'), Domain::literal('b'), Domain::literal('a')], 'b'],
            ['strval', [Domain::literal('a')], 'a'],
            ['intval', [Domain::literal('1')], '{$}'],
            ['count', [Domain::literal('1')], '{$}'],
            ['strlen', [Domain::literal('1')], '{$}'],
            ['json_encode', [Domain::literal('1')], '{$}'],
        ];
    }

    #[DataProvider('providerSupports')]
    public function testSupportsEveryModelledFunction(string $function): void
    {
        self::assertTrue((new BuiltinCallModel())->supports($function));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerSupports(): array
    {
        return [
            ['sprintf'], ['vsprintf'], ['implode'], ['join'], ['str_repeat'],
            ['strtolower'], ['strtoupper'], ['ucfirst'], ['lcfirst'],
            ['trim'], ['ltrim'], ['rtrim'], ['str_replace'], ['strval'],
            ['intval'], ['count'], ['strlen'],
            ['json_encode'], ['addslashes'], ['htmlspecialchars'], ['str_pad'],
            ['substr'], ['number_format'], ['date'], ['ucwords'], ['nl2br'], ['serialize'],
        ];
    }

    public function testEvaluateOfAFunctionItDoesNotModelFallsBackToAString(): void
    {
        self::assertSame('string', (new BuiltinCallModel())->evaluate('addslashes', [Domain::literal('a')])->type()->display());
    }

    public function testSupportsTheFunctionsThatShapeQueryText(): void
    {
        $model = new BuiltinCallModel();
        self::assertTrue($model->supports('sprintf'));
        self::assertTrue($model->supports('\\implode'));
        self::assertTrue($model->supports('json_encode'));
        self::assertFalse($model->supports('array_map'));
    }

    public function testEvaluateResolvesSprintf(): void
    {
        $result = (new BuiltinCallModel())->evaluate('sprintf', [Domain::literal('FROM %s'), Domain::literal('users')]);
        self::assertSame('FROM users', $result->soleLiteral()?->value);
    }

    public function testEvaluateResolvesTheStringTransformations(): void
    {
        $model = new BuiltinCallModel();
        self::assertSame('ABC', $model->evaluate('strtoupper', [Domain::literal('abc')])->soleLiteral()?->value);
        self::assertSame('abc', $model->evaluate('trim', [Domain::literal(' abc ')])->soleLiteral()?->value);
    }

    public function testEvaluateOfAStringReturningFunctionKnowsOnlyTheType(): void
    {
        self::assertSame('string', (new BuiltinCallModel())->evaluate('json_encode', [Domain::literal('x')])->type()->display());
    }

    public function testEvaluateOfACountingFunctionKnowsOnlyTheType(): void
    {
        self::assertSame('int', (new BuiltinCallModel())->evaluate('strlen', [Domain::literal('x')])->type()->display());
    }

    public function testEvaluateOfStrvalPassesTheValueThrough(): void
    {
        self::assertSame('x', (new BuiltinCallModel())->evaluate('strval', [Domain::literal('x')])->soleLiteral()?->value);
    }

    /**
     * @param list<Domain> $arguments
     */
    #[DataProvider('providerEvaluateUnresolved')]
    public function testEvaluateKnowsTheTypeOfAResultItCouldNotResolve(string $function, array $arguments, string $expected): void
    {
        self::assertSame($expected, (new BuiltinCallModel())->evaluate($function, $arguments)->type()->display());
    }

    /**
     * @return array<string, array{string, list<Domain>, string}>
     */
    public static function providerEvaluateUnresolved(): array
    {
        return [
            'intval' => ['intval', [Domain::unknown()], 'int'],
            'count' => ['count', [Domain::unknown()], 'int'],
            'strlen' => ['strlen', [Domain::unknown()], 'int'],
            'sprintf of an unknown format' => ['sprintf', [Domain::unknown()], 'string'],
            'vsprintf without an array' => ['vsprintf', [Domain::literal('%s'), Domain::unknown()], 'string'],
            'implode without an array' => ['implode', [Domain::literal(','), Domain::unknown()], 'string'],
            'str_repeat of an unknown count' => ['str_repeat', [Domain::literal('?'), Domain::unknown()], 'string'],
            'strtolower of an unknown subject' => ['strtolower', [Domain::unknown()], 'string'],
            'str_replace of an unknown subject' => ['str_replace', [Domain::literal('a'), Domain::literal('b'), Domain::unknown()], 'string'],
        ];
    }

    #[DataProvider('providerNormalize')]
    public function testNormalize(string $written, string $expected): void
    {
        self::assertSame($expected, (new BuiltinCallModel())->normalize($written));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerNormalize(): array
    {
        return [
            ['SPRINTF', 'sprintf'],
            ['\\App\\implode', 'app\\implode'],
            ['\\sprintf', 'sprintf'],
        ];
    }

    public function testSprintfLeavesAGapForAnArgumentThatDidNotResolve(): void
    {
        $result = (new BuiltinCallModel())->sprintf([Domain::unknown()], Domain::literal('FROM %s'));
        self::assertSame('FROM {$}', $result->patterns()[0]->display());
    }

    public function testSprintfGivesUpWhenTheFormatDidNotResolve(): void
    {
        self::assertFalse((new BuiltinCallModel())->sprintf([], Domain::unknown())->isExact());
    }

    public function testSprintfOfAFormatThatIsNotTextKeepsItsOriginButNotItsExpression(): void
    {
        $result = (new BuiltinCallModel())->sprintf([Domain::literal('a')], Domain::opaque(TypeShape::unknown(), Origin::External, '$format'));
        $hole = $result->patterns()[0]->holes()[0];

        self::assertSame(Origin::External, $hole->origin);
        self::assertSame('sprintf', $hole->expression);
        self::assertSame('string', $hole->type->display());
    }

    public function testSprintfFillsAPartlyKnownFormat(): void
    {
        $format = Domain::of(new PatternTerm(
            TextPattern::fromText('SELECT * FROM ')
                ->concat(TextPattern::fromHole(new TextHole(Origin::Parameter, TypeShape::of(['string']), '$table')))
                ->concat(TextPattern::fromText(' WHERE id = %d')),
        ));

        $result = (new BuiltinCallModel())->sprintf([Domain::literal(7)], $format);

        self::assertSame('SELECT * FROM {$} WHERE id = 7', $result->patterns()[0]->display());
    }

    public function testSprintfKeepsALiteralPercentSign(): void
    {
        self::assertSame('100%', (new BuiltinCallModel())->sprintf([], Domain::literal('100%%'))->soleLiteral()?->value);
    }

    /**
     * @param list<Domain> $arguments
     */
    #[DataProvider('providerFormatPattern')]
    public function testFormatPatternFillsEachConversionWithTheNextArgument(string $format, array $arguments, string $expected): void
    {
        self::assertSame($expected, (new BuiltinCallModel())->formatPattern(TextPattern::fromText($format), $arguments)->soleLiteral()?->value);
    }

    /**
     * @return list<array{string, list<Domain>, string}>
     */
    public static function providerFormatPattern(): array
    {
        return [
            ['SELECT %s FROM %s', [Domain::literal('id'), Domain::literal('users')], 'SELECT id FROM users'],
            ['LIMIT %d', [Domain::literal(10)], 'LIMIT 10'],
            ['100%%', [], '100%'],
            ['%%%s%%', [Domain::literal('a')], '%a%'],
            ['%05.2f|%-3s|%x', [Domain::literal('1'), Domain::literal('2'), Domain::literal('3')], '1|2|3'],
            ['no conversions', [Domain::literal('unused')], 'no conversions'],
            ['', [Domain::literal('unused')], ''],
        ];
    }

    public function testFormatPatternLeavesAGapForAConversionWithoutAnArgument(): void
    {
        $pattern = (new BuiltinCallModel())->formatPattern(TextPattern::fromText('%s-%s'), [Domain::literal('only')])->patterns()[0];

        self::assertSame('only-{$}', $pattern->display());
        self::assertSame(Origin::Call, $pattern->holes()[0]->origin);
        self::assertSame('mixed', $pattern->holes()[0]->type->display());
        self::assertSame('sprintf', $pattern->holes()[0]->expression);
    }

    public function testFormatPatternCarriesAGapInTheFormatAndKeepsCountingArgumentsPastIt(): void
    {
        $format = TextPattern::fromSegments([
            new LiteralText('SELECT %s FROM '),
            new TextHole(Origin::Parameter, TypeShape::of(['string']), '$table'),
            new LiteralText(' WHERE id = %d'),
        ]);

        $pattern = (new BuiltinCallModel())->formatPattern($format, [Domain::literal('name'), Domain::literal(7)])->patterns()[0];

        self::assertSame('SELECT name FROM {$} WHERE id = 7', $pattern->display());
        self::assertCount(1, $pattern->holes());
        self::assertSame(Origin::Parameter, $pattern->holes()[0]->origin);
        self::assertSame('$table', $pattern->holes()[0]->expression);
    }

    public function testFormatPatternKeepsEveryAlternativeOfAnArgument(): void
    {
        $result = (new BuiltinCallModel())->formatPattern(
            TextPattern::fromText('id = %s'),
            [Domain::literal(1)->union(Domain::literal(2))],
        );

        self::assertSame(
            ['id = 1', 'id = 2'],
            array_map(static fn (TextPattern $pattern): string => $pattern->display(), $result->patterns()),
        );
    }

    public function testOriginOfIsTheOriginOfTheFirstOpaqueAlternative(): void
    {
        $domain = Domain::literal('a')
            ->union(Domain::opaque(TypeShape::of(['string']), Origin::Parameter))
            ->union(Domain::opaque(TypeShape::of(['int']), Origin::External));

        self::assertSame(Origin::Parameter, (new BuiltinCallModel())->originOf($domain, Origin::Call));
    }

    public function testOriginOfFallsBackWhenNothingInTheValueIsOpaque(): void
    {
        $model = new BuiltinCallModel();

        self::assertSame(Origin::Loop, $model->originOf(Domain::literal('a'), Origin::Loop));
        self::assertSame(Origin::Call, $model->originOf(Domain::literal('a'), Origin::Call));
        self::assertSame(Origin::External, $model->originOf(Domain::opaque(TypeShape::unknown(), Origin::External), Origin::Loop));
    }

    public function testVsprintfTakesItsArgumentsFromAnArray(): void
    {
        $values = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('users'))]));
        $result = (new BuiltinCallModel())->vsprintf([Domain::literal('FROM %s'), $values]);
        self::assertSame('FROM users', $result->soleLiteral()?->value);
    }

    public function testVsprintfGivesUpWithoutAKnownArray(): void
    {
        self::assertFalse((new BuiltinCallModel())->vsprintf([Domain::literal('%s'), Domain::unknown()])->isExact());
    }

    public function testSplitFormatSeparatesLiteralsFromConversions(): void
    {
        self::assertSame(['SELECT ', '%s', ' FROM ', '%s'], (new BuiltinCallModel())->splitFormat('SELECT %s FROM %s'));
    }

    public function testSplitFormatListsThePiecesOfAFormatStartingWithAConversion(): void
    {
        self::assertSame(['%s', ' = ', '%d'], (new BuiltinCallModel())->splitFormat('%s = %d'));
    }

    public function testImplodeWithoutGlueJoinsTheElementsDirectly(): void
    {
        $array = Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::literal('a')),
            new ArrayEntry(null, Domain::literal('b')),
        ]));

        self::assertSame('ab', (new BuiltinCallModel())->implode([$array])->soleLiteral()?->value);
    }

    public function testImplodeWithoutAKnownArrayKeepsTheOriginOfThePieces(): void
    {
        $result = (new BuiltinCallModel())->implode([Domain::literal(','), Domain::opaque(TypeShape::unknown(), Origin::External)]);
        $hole = $result->patterns()[0]->holes()[0];

        self::assertSame(Origin::External, $hole->origin);
        self::assertSame('implode', $hole->expression);
    }

    public function testImplodeOfAnIncompleteArrayEndsInAStringGap(): void
    {
        $array = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('id'))], false));
        $holes = (new BuiltinCallModel())->implode([Domain::literal(','), $array])->patterns()[0]->holes();

        self::assertCount(1, $holes);
        self::assertSame('string', $holes[0]->type->display());
        self::assertSame('implode', $holes[0]->expression);
    }

    public function testImplodeJoinsTheElementsOfAKnownArray(): void
    {
        $array = Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::literal('id')),
            new ArrayEntry(null, Domain::literal('name')),
        ]));
        $result = (new BuiltinCallModel())->implode([Domain::literal(', '), $array]);
        self::assertSame('id, name', $result->soleLiteral()?->value);
    }

    public function testImplodeLeavesAGapWhenTheArrayIsIncomplete(): void
    {
        $array = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal('id'))], false));
        self::assertFalse((new BuiltinCallModel())->implode([Domain::literal(','), $array])->isExact());
    }

    public function testImplodeGivesUpWithoutAKnownArray(): void
    {
        self::assertFalse((new BuiltinCallModel())->implode([Domain::literal(','), Domain::unknown()])->isExact());
    }

    public function testArrayArgumentFindsTheArrayInAnyPosition(): void
    {
        $array = Domain::of(new ArrayTerm([]));
        $model = new BuiltinCallModel();
        self::assertNotNull($model->arrayArgument([Domain::literal(','), $array]));
        self::assertNull($model->arrayArgument([Domain::literal(',')]));
    }

    public function testRepeatResolvesAKnownRepetition(): void
    {
        self::assertSame('??', (new BuiltinCallModel())->repeat([Domain::literal('?'), Domain::literal(2)])->soleLiteral()?->value);
    }

    public function testRepeatGivesUpOnAnUnreasonableCount(): void
    {
        $model = new BuiltinCallModel();
        self::assertFalse($model->repeat([Domain::literal('?'), Domain::literal(-1)])->isExact());
        self::assertFalse($model->repeat([Domain::literal('?'), Domain::literal(10000)])->isExact());
        self::assertFalse($model->repeat([Domain::literal('?'), Domain::unknown()])->isExact());
    }

    public function testRepeatResolvesTheBoundsOfAReasonableCount(): void
    {
        $model = new BuiltinCallModel();

        self::assertSame('', $model->repeat([Domain::literal('?'), Domain::literal(0)])->soleLiteral()?->value);
        self::assertSame(str_repeat('?', 1000), $model->repeat([Domain::literal('?'), Domain::literal(1000)])->soleLiteral()?->value);
        self::assertFalse($model->repeat([Domain::literal('?'), Domain::literal(1001)])->isExact());
    }

    public function testTransformGivesUpWithoutAKnownSubject(): void
    {
        self::assertFalse((new BuiltinCallModel())->transform('strtolower', [Domain::unknown()])->isExact());
    }

    public function testTransformCoversEachOneArgumentFunction(): void
    {
        $model = new BuiltinCallModel();
        self::assertSame('abc', $model->transform('strtolower', [Domain::literal('ABC')])->soleLiteral()?->value);
        self::assertSame('Abc', $model->transform('ucfirst', [Domain::literal('abc')])->soleLiteral()?->value);
        self::assertSame('aBC', $model->transform('lcfirst', [Domain::literal('ABC')])->soleLiteral()?->value);
        self::assertSame('a ', $model->transform('ltrim', [Domain::literal(' a ')])->soleLiteral()?->value);
        self::assertSame(' a', $model->transform('rtrim', [Domain::literal(' a ')])->soleLiteral()?->value);
    }

    public function testReplaceResolvesWhenEveryArgumentIsKnown(): void
    {
        $result = (new BuiltinCallModel())->replace([Domain::literal('a'), Domain::literal('b'), Domain::literal('aa')]);
        self::assertSame('bb', $result->soleLiteral()?->value);
    }

    public function testReplaceGivesUpWhenAnArgumentIsNotKnown(): void
    {
        self::assertFalse((new BuiltinCallModel())->replace([Domain::unknown(), Domain::literal('b'), Domain::literal('a')])->isExact());
        self::assertFalse((new BuiltinCallModel())->replace([Domain::literal('a'), Domain::unknown(), Domain::literal('a')])->isExact());
        self::assertFalse((new BuiltinCallModel())->replace([Domain::literal('a'), Domain::literal('b'), Domain::unknown()])->isExact());
    }
    public function testRegisterInstallsModelsIntoAnEmptyRegistry(): void
    {
        $models = new \SqlCatalog\Analysis\FunctionModel\Registry();
        (new BuiltinCallModel())->register($models);
        self::assertTrue($models->supports('implode'));
        self::assertSame('x', $models->evaluate('strval', [Domain::literal('x')])?->soleLiteral()?->value);
    }

}
