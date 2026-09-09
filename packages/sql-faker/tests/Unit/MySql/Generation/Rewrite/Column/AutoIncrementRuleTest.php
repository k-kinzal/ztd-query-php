<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Column\AutoIncrementRule;

#[CoversClass(AutoIncrementRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class AutoIncrementRuleTest extends TestCase
{
    #[DataProvider('providerTypes')]
    public function testRewriteRetainsOnlyTypeCompatibleAttributes(string $name, bool $allowed): void
    {
        $type = new TerminalOccurrence($name, 10, [0, 1], ['field_def', 'type']);
        $attribute = new TerminalOccurrence('AUTO_INC', 11, [0, 2], ['field_def', 'column_attribute']);
        $input = new TerminalSequence([$type, $attribute], productions: [new ProductionOccurrence(0, null, 'field_def', 0), new ProductionOccurrence(1, 0, 'type', 0), new ProductionOccurrence(2, 0, 'column_attribute', 0)]);
        $result = (new AutoIncrementRule())->rewrite($input);
        self::assertSame($allowed ? [$name, 'AUTO_INC'] : [$name], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($type, $result->terminals[0]);
        self::assertSame($result, (new AutoIncrementRule())->rewrite($result));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerTypes(): iterable
    {
        foreach (['INT_SYM', 'TINYINT_SYM', 'SMALLINT_SYM', 'MEDIUMINT_SYM', 'BIGINT_SYM', 'BOOL_SYM', 'BOOLEAN_SYM'] as $name) {
            yield [$name, true];
        }
        foreach (['BIT_SYM', 'FLOAT_SYM', 'DOUBLE_SYM', 'DECIMAL_SYM', 'CHAR_SYM', 'BINARY_SYM', 'TINYBLOB_SYM', 'YEAR_SYM'] as $name) {
            yield [$name, false];
        }
    }

    public function testRewritePreservesAttributesWithoutAnOwningType(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('AUTO_INC', 10, [0], ['column_attribute'])], productions: [new ProductionOccurrence(0, null, 'column_attribute', 0)]);
        self::assertSame($input, (new AutoIncrementRule())->rewrite($input));
    }
}
