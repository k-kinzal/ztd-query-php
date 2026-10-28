<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\ContextualReading;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;

#[CoversClass(ContextualReading::class)]
#[CoversClass(\SqlSemantics\Platform\MySql\Lowering\Expression\ReadingVisit::class)]
#[Small]
final class ContextualReadingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerOrders(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $reversed = $release === '5.6.51' ? ['bad', 'missing'] : ['missing', 'bad'];
            yield $release . ' select' => [$release, "SELECT @@missing, DATE 'bad', TIME '01:02:03', @a", ['missing', 'bad', '01:02:03', 'a']];
            yield $release . ' trim' => [$release, "SELECT TRIM(DATE 'bad' FROM @@missing)", $reversed];
            yield $release . ' add' => [$release, "SELECT TIMESTAMPADD(DAY, DATE 'bad', @@missing)", $reversed];
            yield $release . ' assign' => [$release, "SELECT @a := DATE 'bad'", $release === '5.6.51' ? ['a', 'bad'] : ['bad', 'a']];
            yield $release . ' diff' => [$release, "SELECT TIMESTAMPDIFF(DAY, DATE 'bad', @@missing)", ['bad', 'missing']];
        }
    }

    /**
     * @param list<string> $expected Contextual operands in their interpretation order
     */
    #[DataProvider('providerOrders')]
    public function testReadRetainsTypedValuesAndTheirInputRanges(string $release, string $sql, array $expected): void
    {
        $platform = new Platform();
        $profile = $platform->profile($release, null, ParameterStyle::Native);
        $values = (new ContextualReading())->read($platform->parser($profile)->parse($sql), $profile);
        self::assertSame($expected, array_map(static fn (array $value): string => $value[2] instanceof SystemVariable || $value[2] instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable ? $value[2]->name->value : $value[2]->text, $values));
        self::assertSame(count($expected), count(array_filter($values, static fn (array $value): bool => $value[0] >= 0 && $value[1] > $value[0] && $value[1] <= strlen($sql))));
    }

    public function testChildrenUsesTheSameTrimOperandRolesAsLowering(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse("SELECT TRIM(DATE 'bad' FROM @@missing)");
        $children = (new ContextualReading())->children($tree->find('function_call_keyword')[0], $lowering);
        self::assertCount(2, $children);
        self::assertCount(1, $children[0]->find('rvalue_system_or_user_variable'));
        self::assertCount(1, $children[1]->find('temporal_literal'));
    }

    public function testValueRetainsInvalidAndValidLiteralOperands(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse("SELECT DATE 'bad', DATE '2026-10-20', @@missing, @a");
        $reader = new ContextualReading();
        $invalid = $reader->value($tree->find('temporal_literal')[0], $lowering);
        self::assertInstanceOf(TemporalLiteral::class, $invalid);
        self::assertFalse($invalid->valid());
        self::assertSame('bad', $invalid->text);
        self::assertInstanceOf(TemporalLiteral::class, $reader->value($tree->find('temporal_literal')[1], $lowering));
        self::assertInstanceOf(SystemVariable::class, $reader->value($tree->find('rvalue_system_or_user_variable')[0], $lowering));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable::class, $reader->value($tree->find('rvalue_system_or_user_variable')[1], $lowering));
        self::assertNull($reader->value($tree, $lowering));
    }
}
