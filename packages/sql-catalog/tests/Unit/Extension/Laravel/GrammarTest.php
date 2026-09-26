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


    public function testWrapKeepsEachAlternativeAndTheOriginOfUnresolvedNames(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql'));
        $either = $grammar->wrap(Domain::literal('a')->union(Domain::literal('b')));
        self::assertSame(['`a`', '`b`'], array_map(static fn (\SqlCatalog\Core\Evaluation\Term $term): mixed => $term instanceof LiteralTerm ? $term->value : null, $either->terms));
        $parameter = $grammar->wrap(Domain::opaque(TypeShape::of(['string']), \SqlCatalog\Core\Text\Origin::Parameter, '$column'));
        self::assertSame(\SqlCatalog\Core\Text\Origin::Parameter, $parameter->patterns()[0]->holes()[0]->origin);
        self::assertFalse($grammar->wrap(Domain::literal(1))->isExact());
    }

    public function testWrapNameQuotesQualifiedNamesAndLeavesJsonSelectorsOpen(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('pgsql'));
        self::assertSame('"users"."id" as "uid"', $grammar->wrapName('users.id as uid')->soleLiteral()?->value);
        self::assertFalse($grammar->wrapName('data->name')->isExact());
        self::assertFalse((new Grammar(null))->wrapName('users')->isExact());
    }

    public function testOpenedKeepsNonLiteralTermsAndRejectsNonStringLiterals(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        $opaque = new OpaqueTerm(TypeShape::of(['string']), \SqlCatalog\Core\Text\Origin::External, '$_GET');
        self::assertSame([$opaque], $grammar->opened($opaque)->terms);
        self::assertSame(\SqlCatalog\Core\Text\Origin::Call, $grammar->opened(new LiteralTerm(7))->patterns()[0]->holes()[0]->origin);
    }

    public function testPlaceholdersStandForAListOfUnknownLengthFromTheSameOrigin(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        $hole = $grammar->placeholders(Domain::of(new OpaqueTerm(TypeShape::of(['array']), \SqlCatalog\Core\Text\Origin::External, '$_GET', 'ids')))->patterns()[0]->holes()[0];
        self::assertSame(\SqlCatalog\Core\Text\Origin::External, $hole->origin);
        self::assertSame('ids', $hole->variable);
        self::assertSame(\SqlCatalog\Core\Text\Origin::Unresolved, $grammar->placeholders(Domain::of(new ArrayTerm([], false)))->patterns()[0]->holes()[0]->origin);
        self::assertSame(\SqlCatalog\Core\Text\Origin::Branch, $grammar->placeholders(Domain::literal('a')->union(Domain::literal('b')))->patterns()[0]->holes()[0]->origin);
    }

    public function testElementIsOneUnknownValueFromTheListsOrigin(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        $element = $grammar->element(Domain::opaque(TypeShape::of(['array']), \SqlCatalog\Core\Text\Origin::Parameter, '$values'));
        self::assertSame(\SqlCatalog\Core\Text\Origin::Parameter, $element->patterns()[0]->holes()[0]->origin);
        self::assertTrue($element->type()->isUnknown());
        self::assertSame(\SqlCatalog\Core\Text\Origin::Unresolved, $grammar->element(Domain::of(new ArrayTerm([], false)))->patterns()[0]->holes()[0]->origin);
    }

    public function testRawBindingsCountPlaceholdersWhenTheArrayIsUnknown(): void
    {
        $grammar = new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'));
        $known = $grammar->rawBindings(Domain::literal('a = ?'), QueryState::list([Domain::literal(1)]));
        self::assertSame([1], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $known ?? []));
        self::assertCount(2, $grammar->rawBindings(Domain::literal('a = ? or b = ?'), Domain::unknown()) ?? []);
        self::assertSame([], $grammar->rawBindings(Domain::literal('a = 1'), Domain::of(new ArrayTerm([], false))));
        self::assertNull($grammar->rawBindings(Domain::unknown(), Domain::unknown()));
        self::assertNull($grammar->rawBindings(Domain::literal('a = ?'), Domain::of(new ObjectTerm('Closure'))));
    }
}
