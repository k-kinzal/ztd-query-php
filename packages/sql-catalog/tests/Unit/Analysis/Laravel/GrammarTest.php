<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Laravel\Grammar;
use SqlCatalog\Analysis\Laravel\QueryState;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Grammar::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(QueryState::class)]
final class GrammarTest extends TestCase
{
    public function testWrapQuotesIdentifiersAliasesAndEmbeddedQuotes(): void
    {
        self::assertSame('`users`.`id`', (new Grammar('mysql'))->wrap(Domain::literal('users.id'))->soleLiteral()?->value);
        self::assertSame('"users" as "u"', (new Grammar('pgsql'))->wrap(Domain::literal('users AS u'))->soleLiteral()?->value);
        self::assertSame('"odd""name"', (new Grammar('sqlite'))->wrap(Domain::literal('odd"name'))->soleLiteral()?->value);
        self::assertSame('"users".*', (new Grammar('sqlite'))->wrap(Domain::literal('users.*'))->soleLiteral()?->value);
        self::assertFalse((new Grammar(null))->wrap(Domain::literal('users'))->isExact());
        self::assertFalse((new Grammar('sqlite'))->wrap(Domain::unknown())->isExact());
        self::assertFalse((new Grammar('sqlite'))->wrap(Domain::literal('data->name'))->isExact());
    }

    public function testJoinPreservesOrderAndGaps(): void
    {
        $grammar = new Grammar('sqlite');
        self::assertSame('a / b', $grammar->join([Domain::literal('a'), Domain::literal('b')], ' / ')->soleLiteral()?->value);
        self::assertFalse($grammar->join([Domain::literal('a'), Domain::unknown()])->isExact());
        self::assertSame('', $grammar->join([])->soleLiteral()?->value);
    }

    public function testParameterOnlySplicesExplicitExpressions(): void
    {
        $grammar = new Grammar('sqlite');
        $raw = Domain::of(new ObjectTerm('Illuminate\Database\Query\Expression', state: (new QueryState(['sql' => Domain::literal('count(*)')]))->array()));
        self::assertSame('count(*)', $grammar->parameter($raw)->soleLiteral()?->value);
        self::assertSame('count(*)', $grammar->wrap($raw)->soleLiteral()?->value);
        self::assertSame('?', $grammar->parameter(Domain::literal('untrusted'))->soleLiteral()?->value);
    }

    public function testBindingsExcludeExpressionsWithoutChangingValueOrder(): void
    {
        $a = Domain::literal(1);
        $b = Domain::literal(null);
        $raw = Domain::of(new ObjectTerm('Illuminate\Database\Query\Expression'));
        self::assertSame([$a, $b], (new Grammar('mysql'))->bindings([$a, $raw, $b]));
    }
    public function testWrapTreatsAnAliasAsOneIdentifierEvenWhenItContainsDots(): void
    {
        self::assertSame('"users"."id" as "user.key"', (new Grammar('sqlite'))->wrap(Domain::literal('users.id as user.key'))->soleLiteral()?->value);
        self::assertSame('`users`.`id` as `odd``alias`', (new Grammar('mysql'))->wrap(Domain::literal('users.id as odd`alias'))->soleLiteral()?->value);
    }

}
