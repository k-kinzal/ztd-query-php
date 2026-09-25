<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\QueryState;

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
        self::assertSame('`users`.`id`', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')))->wrap(Domain::literal('users.id'))->soleLiteral()?->value);
        self::assertSame('"users" as "u"', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('pgsql')))->wrap(Domain::literal('users AS u'))->soleLiteral()?->value);
        self::assertSame('"odd""name"', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')))->wrap(Domain::literal('odd"name'))->soleLiteral()?->value);
        self::assertSame('"users".*', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')))->wrap(Domain::literal('users.*'))->soleLiteral()?->value);
        self::assertFalse((new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find(null)))->wrap(Domain::literal('users'))->isExact());
        self::assertFalse((new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')))->wrap(Domain::unknown())->isExact());
        self::assertFalse((new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')))->wrap(Domain::literal('data->name'))->isExact());
    }

    public function testJoinPreservesOrderAndGaps(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        self::assertSame('a / b', $grammar->join([Domain::literal('a'), Domain::literal('b')], ' / ')->soleLiteral()?->value);
        self::assertFalse($grammar->join([Domain::literal('a'), Domain::unknown()])->isExact());
        self::assertSame('', $grammar->join([])->soleLiteral()?->value);
    }

    public function testParameterOnlySplicesExplicitExpressions(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
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
        self::assertSame([$a, $b], (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')))->bindings([$a, $raw, $b]));
    }
    public function testWrapTreatsAnAliasAsOneIdentifierEvenWhenItContainsDots(): void
    {
        self::assertSame('"users"."id" as "user.key"', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')))->wrap(Domain::literal('users.id as user.key'))->soleLiteral()?->value);
        self::assertSame('`users`.`id` as `odd``alias`', (new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')))->wrap(Domain::literal('users.id as odd`alias'))->soleLiteral()?->value);
    }

}
