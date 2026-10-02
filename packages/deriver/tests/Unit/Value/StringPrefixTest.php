<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\StringPrefix;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringPrefix::class)]
#[UsesClass(Term::class)]
#[Small]
final class StringPrefixTest extends TestCase
{
    /**
     * @param Term $value String term
     * @param array{string, bool} $expected Known leading bytes and completeness
     */
    #[DataProvider('providerKnown')]
    public function testKnownReadsLeadingConstantBytes(Term $value, array $expected): void
    {
        self::assertSame($expected, (new StringPrefix())->known($value));
    }

    /**
     * @return iterable<string, array{Term, array{string, bool}}>
     */
    public static function providerKnown(): iterable
    {
        $unknown = Term::parameter('x', 'string');
        $concat = static fn (Term $a, Term $b): Term => new Term('concat', operands: [$a, $b], attributes: ['type' => 'string']);
        yield 'constant' => [Term::constant('abc'), ['abc', true]];
        yield 'non-string constant' => [Term::constant(1), ['', false]];
        yield 'symbolic' => [$unknown, ['', false]];
        yield 'constant then symbolic' => [$concat(Term::constant('a'), $unknown), ['a', false]];
        yield 'nested constants' => [$concat($concat(Term::constant('a'), Term::constant('b')), $concat(Term::constant('c'), $unknown)), ['abc', false]];
        yield 'symbolic first' => [$concat($unknown, Term::constant('a')), ['', false]];
        yield 'string cast of a concat' => [new Term('cast', 'string', [$concat(Term::constant('a'), $unknown)], ['type' => 'string']), ['a', false]];
        yield 'string cast of a parameter' => [new Term('cast', 'string', [$unknown], ['type' => 'string']), ['', false]];
    }

    public function testWidenKeepsTheCommonPrefixAsAnOpenString(): void
    {
        $prefix = new StringPrefix();
        $widened = $prefix->widen(Term::constant('SELECT a'), Term::constant('SELECT b'));
        self::assertNotNull($widened);
        self::assertSame('SELECT ', $prefix->bound($widened));
        self::assertTrue($prefix->contains('SELECT ', Term::constant('SELECT a')));
        self::assertFalse($prefix->contains('SELECT ', Term::constant('DELETE')));
        self::assertNull($prefix->widen(Term::constant('a'), Term::constant('b')));
        self::assertNull($prefix->widen(Term::constant(''), Term::constant('b')));
    }

    public function testWidenOnlyShrinksThePrefix(): void
    {
        $prefix = new StringPrefix();
        $widened = $prefix->widen(Term::constant('SELECT a'), Term::constant('SELECT a AND b'));
        self::assertNotNull($widened);
        self::assertSame('SELECT a', $prefix->bound($widened));
        $next = new Term('concat', operands: [$widened, Term::constant(' AND c')], attributes: ['type' => 'string']);
        self::assertTrue($prefix->contains('SELECT a', $next));
        $shorter = $prefix->widen($widened, Term::constant('SELECT x'));
        self::assertNotNull($shorter);
        self::assertSame('SELECT ', $prefix->bound($shorter));
    }

    public function testContainsRequiresTheKnownLeadingBytes(): void
    {
        $prefix = new StringPrefix();
        $open = new Term('concat', operands: [Term::constant('SELECT a'), Term::parameter('x', 'string')], attributes: ['type' => 'string']);
        self::assertTrue($prefix->contains('SELECT', $open));
        self::assertTrue($prefix->contains('SELECT a', $open));
        self::assertFalse($prefix->contains('SELECT ab', $open));
        self::assertFalse($prefix->contains('S', Term::parameter('x', 'string')));
    }

    public function testWidenRetainsConfidentiality(): void
    {
        $widened = (new StringPrefix())->widen(Term::constant('key:1', true), Term::constant('key:2'));
        self::assertNotNull($widened);
        self::assertTrue($widened->isSecret());
        self::assertTrue($widened->operands[0]->secret);
    }

    public function testBoundRecognizesOnlyWidenedStrings(): void
    {
        $prefix = new StringPrefix();
        $open = new Term('abstract', 'WIDENED', attributes: ['type' => 'string']);
        self::assertSame('a', $prefix->bound(new Term('concat', operands: [Term::constant('a'), $open], attributes: ['type' => 'string'])));
        self::assertNull($prefix->bound(new Term('concat', operands: [Term::constant(''), $open], attributes: ['type' => 'string'])));
        self::assertNull($prefix->bound(new Term('concat', operands: [Term::constant('a'), Term::parameter('x', 'string')], attributes: ['type' => 'string'])));
        self::assertNull($prefix->bound(new Term('concat', operands: [Term::constant('a'), new Term('abstract', 'WIDENED', attributes: ['type' => 'mixed'])], attributes: ['type' => 'string'])));
        self::assertNull($prefix->bound(Term::constant('a')));
    }
}
