<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TermKind::class)]
#[UsesClass(Term::class)]
final class TermKindTest extends TestCase
{
    #[DataProvider('providerExpressions')]
    public function testKindsIdentifyConcreteAndPartialExpressions(Term $term, TermKind $kind, bool $concrete): void
    {
        self::assertSame($kind, $term->kind);
        self::assertSame($concrete, $term->isConcrete());
    }

    /**
     * @return iterable<string, array{Term, TermKind, bool}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'constant' => [Term::constant(42), TermKind::Constant, true];
        yield 'array' => [Term::array(['answer' => Term::constant(42)]), TermKind::Array, true];
        yield 'parameter' => [Term::parameter('input'), TermKind::Parameter, false];
        yield 'opaque' => [Term::opaque('MISSING_SOURCE'), TermKind::Opaque, false];
        yield 'partial array' => [Term::array(['input' => Term::parameter('input')]), TermKind::Array, false];
    }
}
