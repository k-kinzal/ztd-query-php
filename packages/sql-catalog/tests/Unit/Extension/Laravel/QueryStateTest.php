<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(QueryState::class)]
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
final class QueryStateTest extends TestCase
{
    public function testGetLeavesUnsetFieldsNull(): void
    {
        self::assertNull((new QueryState())->get('limit')->soleLiteral()?->value);
    }

    public function testWithPreservesTheOriginalState(): void
    {
        $before = new QueryState(['table' => Domain::literal('users')]);
        $after = $before->with('table', Domain::literal('posts'));
        self::assertSame('users', $before->string('table'));
        self::assertSame('posts', $after->string('table'));
    }

    public function testStringRejectsNonStringDomains(): void
    {
        self::assertNull((new QueryState(['limit' => Domain::literal(1)]))->string('limit'));
        self::assertNull((new QueryState(['table' => Domain::unknown()]))->string('table'));
    }

    public function testItemsRetainsOrderedBindings(): void
    {
        $values = [Domain::literal('first'), Domain::literal(2)];
        self::assertSame($values, (new QueryState(['whereBindings' => QueryState::list($values)]))->items('whereBindings'));
        self::assertSame([], (new QueryState())->items('whereBindings'));
    }

    public function testAppendPreservesEarlierBindings(): void
    {
        $state = (new QueryState())->append('whereBindings', [Domain::literal(1)])->append('whereBindings', [Domain::literal(2)]);
        self::assertSame([1, 2], array_map(static fn (Domain $value): mixed => $value->soleLiteral()?->value, $state->items('whereBindings')));
    }

    public function testListPreservesUnknownValuesAsBoundParameters(): void
    {
        $value = Domain::unknown('request value');
        self::assertSame([$value], QueryState::list([$value])->soleArray()?->positional());
        self::assertTrue(QueryState::list([])->soleArray()?->complete);
    }

    public function testRejectCannotBeErasedByAValidMutation(): void
    {
        $state = (new QueryState())->reject('unknown scope')->with('table', Domain::literal('users'));
        self::assertFalse($state->get('problem')->isExact());
        self::assertSame('users', $state->string('table'));
    }

    public function testFromKeepsAnUninitializedBuilderOpen(): void
    {
        self::assertFalse(QueryState::from(new ObjectTerm('Builder'))->get('problem')->isExact());
    }

    public function testArrayRoundTripsMetadataAndBindingDomains(): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'limit' => Domain::literal(1)]);
        self::assertSame($state->fields, $state->array()->named());
    }

    public function testObjectPreservesAllocationIdentity(): void
    {
        $object = new ObjectTerm('Builder', identity: 'allocation');
        $updated = (new QueryState(['table' => Domain::literal('users')]))->object($object);
        self::assertSame('allocation', $updated->identity);
        self::assertSame('Builder', $updated->className);
        self::assertSame('users', QueryState::from($updated)->string('table'));
        self::assertNull($object->state);
    }
}
