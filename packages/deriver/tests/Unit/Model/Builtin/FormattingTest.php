<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\Formatting;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Builtin\Formatting
 */
#[CoversClass(Formatting::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class FormattingTest extends TestCase
{
    public function testApplyPreservesPositionalArgumentsAndEscapedPercents(): void
    {
        self::assertSame('id:7/%/x', (new Formatting())->apply([Term::constant('id:%2$d/%%/%1$s'), Term::fromNative(['x', 7])])->native());
    }
    public function testApplyReportsMissingArguments(): void
    {
        $value = (new Formatting())->apply([Term::constant('%s'), Term::array([])]);
        self::assertSame('throwable', $value->kind);
        self::assertSame('ArgumentCountError', $value->literal);
    }
    /**
     * @param string $format Literal format
     * @param list<string> $arguments Format arguments
     * @param bool $vector Whether the arguments come from vsprintf
     * @param string $expected PHP 8.3 result or exception class
     */
    #[DataProvider('providerArgumentRules')]
    public function testApplyFollowsTargetArgumentNumberingAndMissingArgumentRules(string $format, array $arguments, bool $vector, string $expected): void
    {
        $value = (new Formatting())->apply([Term::constant($format), Term::fromNative($arguments)], $vector);
        self::assertSame($expected, $value->kind === 'throwable' ? $value->literal : $value->native());
    }

    /**
     * @return iterable<string, array{string, array<int|string, string>, bool, string}> PHP 8.3 results
     */
    public static function providerArgumentRules(): iterable
    {
        yield 'vector values ignore keys' => ['%2$s', ['b' => 'x', 'a' => 'y'], true, 'y'];
        yield 'vector missing item' => ['%s %s', ['x'], true, 'ValueError'];
        yield 'variadic missing argument' => ['%s %s', ['x'], false, 'ArgumentCountError'];
        yield 'zero argument number' => ['%0$s', ['x'], false, 'ValueError'];
        yield 'too large argument number' => ['%2147483647$s', ['x'], false, 'ValueError'];
        yield 'leading zero argument number' => ['%01$s', ['x'], false, 'x'];
        yield 'numbered percent needs its argument' => ['%1$%', [], false, 'ArgumentCountError'];
        yield 'numbered percent' => ['%1$%', ['x'], false, '%'];
        yield 'later invalid number wins over missing argument' => ['%s %0$s', [], false, 'ValueError'];
        yield 'positional does not advance sequence' => ['%1$s %s', ['x'], false, 'x x'];
        yield 'variadic named argument' => ['%s', ['x' => 'v'], false, 'ArgumentCountError'];
        yield 'missing percent argument rereads its percent' => ['%2$%', ['x'], false, 'ValueError'];
        yield 'present second percent argument' => ['%2$%', ['x', 'y'], false, '%'];
        yield 'empty argument number' => ['%$s', ['x'], false, 'ValueError'];
        yield 'argument number at the end' => ['%1$', ['x'], false, 'ValueError'];
        yield 'missing argument number at the end' => ['%1$', [], false, 'ArgumentCountError'];
        yield 'percent at the end' => ['a%', ['x'], false, 'ValueError'];
        yield 'missing percent at the end' => ['a%', [], false, 'ArgumentCountError'];
        yield 'percent conversion before text' => ['%1$%%s', ['x'], false, '%x'];
    }

    public function testRenderDefersMissingArgumentsAndStopsAtPartialBoundaries(): void
    {
        $formatting = new Formatting();
        self::assertSame('ArgumentCountError', $formatting->render('%s %s', false, [Term::constant('a')], false, false)?->literal);
        self::assertSame('ValueError', $formatting->render('%s %s', false, [Term::constant('a')], false, true)?->literal);
        self::assertNull($formatting->render('%s %s', false, [Term::constant('a')], true, false));
        self::assertSame('a %', $formatting->render('%s %%%', false, [Term::constant('a')], true, false)?->native());
        self::assertSame('ArgumentCountError', $formatting->render('%s %', false, [Term::constant('a')], false, false)?->literal);
        self::assertSame('ValueError', $formatting->render('%s %', false, [Term::constant('a'), Term::constant('b')], false, false)?->literal);
        self::assertNull($formatting->render('%5s', false, [Term::constant('a')], false, false));
        self::assertTrue($formatting->render('%s', true, [Term::constant('a')], false, false)?->isSecret());
    }

    public function testPrefixFormatsCompleteSpecifiersBeforeTheUnknownRest(): void
    {
        $rest = new Term('cast', 'string', [Term::parameter('f')], ['type' => 'string']);
        $format = new Term('concat', operands: [new Term('concat', operands: [Term::constant('SELECT %s '), Term::constant('%d%% ')], attributes: ['type' => 'string']), $rest], attributes: ['type' => 'string']);
        self::assertSame('SELECT id 7% ', (new Formatting())->prefix([$format, Term::fromNative(['id', '7x'])])?->native());
    }

    /**
     * @param string $known Known leading format text
     * @param list<string> $arguments Format arguments
     * @param string|null $expected Formatted prefix, or null when none is sound
     */
    #[DataProvider('providerPrefixBoundaries')]
    public function testPrefixStopsBeforeSpecifiersThatMayContinueInTheUnknownRest(string $known, array $arguments, ?string $expected): void
    {
        $format = new Term('concat', operands: [Term::constant($known), new Term('cast', 'string', [Term::parameter('f')], ['type' => 'string'])], attributes: ['type' => 'string']);
        self::assertSame($expected, (new Formatting())->prefix([$format, Term::fromNative($arguments)])?->native());
    }

    /**
     * @return iterable<string, array{string, list<string>, string|null}>
     */
    public static function providerPrefixBoundaries(): iterable
    {
        yield 'trailing percent' => ['100%', [], '100'];
        yield 'trailing argument number' => ['a %1', ['x'], 'a '];
        yield 'trailing argument number sign' => ['a %1$', ['x'], 'a '];
        yield 'complete numbered specifier' => ['%1$s', ['x'], 'x'];
        yield 'unsupported specifier' => ['%05d', ['7'], null];
        yield 'missing argument' => ['%s', [], null];
        yield 'invalid argument number' => ['%0$s', ['x'], null];
        yield 'no known text' => ['', ['x'], null];
    }

    public function testPrefixRejectsNamedArgumentsOnlyForVariadicFormatting(): void
    {
        $format = new Term('concat', operands: [Term::constant('%s '), new Term('cast', 'string', [Term::parameter('f')], ['type' => 'string'])], attributes: ['type' => 'string']);
        self::assertNull((new Formatting())->prefix([$format, Term::fromNative(['x' => 'v'])]));
        self::assertSame('v ', (new Formatting())->prefix([$format, Term::fromNative(['x' => 'v'])], true)?->native());
    }

    public function testSpecifierParsesOnlyArgumentNumbersAndSupportedConversions(): void
    {
        $formatting = new Formatting();
        self::assertSame(['argument' => 1, 'invalid' => false, 'end' => 3, 'conversion' => '%'], $formatting->specifier('%2$%', 1));
        self::assertSame(['argument' => null, 'invalid' => true, 'end' => 2, 'conversion' => 's'], $formatting->specifier('%$s', 1));
        self::assertSame(['argument' => null, 'invalid' => false, 'end' => 1, 'conversion' => ''], $formatting->specifier('%', 1));
        self::assertNull($formatting->specifier('%-5s', 1));
        self::assertNull($formatting->specifier('%x', 1));
    }

    public function testPrefixRequiresAConcatenatedFormatAndKnownArguments(): void
    {
        $formatting = new Formatting();
        self::assertNull($formatting->prefix([Term::constant('%s'), Term::fromNative(['x'])]));
        $format = new Term('concat', operands: [new Term('cast', 'string', [Term::parameter('f')], ['type' => 'string']), Term::constant('%s')], attributes: ['type' => 'string']);
        self::assertNull($formatting->prefix([$format, Term::fromNative(['x'])]));
        self::assertNull($formatting->prefix([new Term('concat', operands: [Term::constant('a'), Term::parameter('f')]), Term::array([], true)]));
    }

    public function testConvertKeepsNumberedPercentsIndependentOfTheArgumentValue(): void
    {
        $formatting = new Formatting();
        self::assertSame('%', $formatting->convert('%', Term::parameter('object', 'object'))?->native());
        self::assertNull($formatting->convert('s', Term::parameter('object', 'object')));
        self::assertSame('cast', $formatting->convert('d', Term::parameter('n', 'int'))?->kind);
    }

    public function testApplyRetainsUnsupportedFormatsAsResiduals(): void
    {
        $value = (new Formatting())->apply([Term::constant('%08d'), Term::fromNative([7])]);
        self::assertSame('opaque', $value->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $value->literal);
    }
}
