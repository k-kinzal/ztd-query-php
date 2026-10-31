<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Conditions;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Conditions::class)]
#[Small]
final class ConditionsTest extends TestCase
{
    public function testAllStartsWithoutAnyConditions(): void
    {
        self::assertSame([], (new Conditions())->all());
    }

    public function testReleaseLeavesAnUnrecordedDefinitionUnchanged(): void
    {
        $conditions = new Conditions();
        $conditions->release(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1'));
        self::assertSame([], $conditions->all());
    }

    public function testReportAndAllKeepConditionIdentityAndOrder(): void
    {
        $conditions = new Conditions();
        $first = new MissingTable(new QualifiedName(new Name('a')));
        $second = new MissingTable(new QualifiedName(new Name('b')));
        $conditions->report($first);
        $conditions->report($second);

        self::assertSame([$first, $second], $conditions->all());
    }

    public function testQueryAttachesOnlyItsOwnConditionsAndRetainsUnchangedFacts(): void
    {
        $conditions = new Conditions();
        $outside = new MissingTable(new QualifiedName(new Name('outside')));
        $inside = new MissingTable(new QualifiedName(new Name('inside')));
        $conditions->report($outside);
        $fact = new QueryFact([], Comparison::Sensitive);
        $derived = $conditions->query(static function () use ($conditions, $inside, $fact): QueryFact {
            $conditions->report($inside);
            return $fact;
        });

        self::assertSame([$inside], $derived->diagnostics);
        self::assertSame([$outside, $inside], $conditions->all());
        self::assertSame($fact, $conditions->query(static fn (): QueryFact => $fact));
    }

    public function testDeferredWithholdsUntilReleaseAndReportsOnlyOnce(): void
    {
        $conditions = new Conditions();
        $definition = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $problem = new MissingTable(new QualifiedName(new Name('t')));
        $result = $conditions->deferred($definition, static function () use ($conditions, $problem): int {
            $conditions->report($problem);
            return 7;
        });
        self::assertSame(7, $result);
        self::assertSame([], $conditions->all());
        $conditions->release($definition);
        $conditions->release($definition);
        self::assertSame([$problem], $conditions->all());
    }
    public function testReleasePreservesDependenciesReferencedByAnUnusedDefinition(): void
    {
        $conditions = new Conditions();
        $first = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $unused = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('2');
        $problem = new MissingTable(new QualifiedName(new Name('t')));
        $conditions->deferred($first, static fn () => $conditions->report($problem));
        $conditions->deferred($unused, static fn () => $conditions->release($first));
        self::assertSame([], $conditions->all());
        $conditions->release($first);
        self::assertSame([$problem], $conditions->all());
        $conditions->release($unused);
        self::assertSame([$problem], $conditions->all());
    }

    public function testDeferredCapturesDependenciesAlreadyReachedInItsEnclosingScope(): void
    {
        $conditions = new Conditions();
        $first = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $second = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('2');
        $outer = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('3');
        $problem = new MissingTable(new QualifiedName(new Name('t')));
        $conditions->deferred($first, static fn () => $conditions->report($problem));
        $conditions->deferred($outer, static function () use ($conditions, $first, $second): void {
            $conditions->release($first);
            $conditions->deferred($second, static fn () => $conditions->release($first));
        });
        self::assertSame([], $conditions->all());
        $conditions->release($second);
        self::assertSame([$problem], $conditions->all());
        $conditions->release($outer);
        self::assertSame([$problem], $conditions->all());
    }
}
