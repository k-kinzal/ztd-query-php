<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Partition\DefinitionShape;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;
use SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DefinitionShape::class)]
#[Small]
final class DefinitionShapeTest extends TestCase
{
    public function testCheckRejectsAnExplicitListAfterAnImplicitOne(): void
    {
        $definitions = [new PartitionDefinition(new Name('p0'), null, [], []), new PartitionDefinition(new Name('p1'), null, [], [new SubpartitionDefinition(new Name('s1'), [])])];
        $this->expectExceptionMessage('Wrong number of subpartitions defined, mismatch with previous setting');
        (new DefinitionShape())->check($definitions);
    }

    public function testProblemChecksTheMethodBeforeSubpartitionCounts(): void
    {
        $definition = new PartitionDefinition(new Name('p'), null, [], []);
        $method = new ExpressionMethod(PartitionKind::Range, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        self::assertSame(DefinitionIssue::MissingRangeValues, (new DefinitionShape())->problem($definition, $method, 2));
        self::assertSame(DefinitionIssue::SubpartitionCount, (new DefinitionShape())->problem($definition, null, 2));
    }

    /**
     * @return iterable<string, array{PartitionMethod, Numeral|null, list<PartitionDefinition>, string|null}>
     */
    public static function providerClauses(): iterable
    {
        $range = new ExpressionMethod(PartitionKind::Range, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $hash = new HashMethod(false, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $bounded = new PartitionDefinition(new Name('p0'), new LessThan(null), [], []);
        $open = new PartitionDefinition(new Name('p1'), null, [], []);
        yield 'range without definitions' => [$range, null, [], 'For RANGE partitions each partition must be defined.'];
        yield 'range definition without values' => [$range, null, [$bounded, $open], DefinitionIssue::MissingRangeValues->message()];
        yield 'hash definition with range values' => [$hash, null, [$bounded], DefinitionIssue::RangeValues->message()];
        yield 'values before the count' => [$range, new Numeral('3'), [$open], DefinitionIssue::MissingRangeValues->message()];
        yield 'count differing from the definitions' => [$hash, new Numeral('3'), [$open], DefinitionIssue::PartitionCount->message()];
        yield 'count equal to the definitions' => [$hash, new Numeral('1'), [$open], null];
        yield 'hash without definitions' => [$hash, new Numeral('3'), [], null];
        yield 'bounded range' => [$range, new Numeral('1'), [$bounded], null];
    }

    /**
     * @param list<PartitionDefinition> $definitions The definitions in order
     */
    #[DataProvider('providerClauses')]
    public function testClauseAnswersTheFirstConditionTheServerRefuses(PartitionMethod $method, ?Numeral $count, array $definitions, ?string $message): void
    {
        self::assertSame($message, (new DefinitionShape())->clause($method, $count, $definitions)?->message());
    }
}
