<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

/**
 * Checks that decoded literal values do not depend on the session's escape mode.
 */
#[CoversNothing]
#[Medium]
final class StringLiteralTest extends TestCase
{
    /**
     * @return iterable<string, array{non-empty-list<string>}>
     */
    public static function providerSegments(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            $choices = new Choices('literal:' . $seed);
            $segments = [''];
            for ($index = 0, $length = $choices->integer(0, 80); $index < $length; $index++) {
                $position = $choices->integer(0, count($segments));
                $segments[$position] = ($segments[$position] ?? '') . $choices->one(['a', '\\', "'", '"', "\0", "\n", "\r", "\x1A", '%', '_', '日本語']);
            }
            yield (string) $seed => [array_values($segments)];
        }
    }

    /**
     * The same structure executes to the same decoded bytes in both modes.
     *
     * @param non-empty-list<string> $segments
     */
    #[DataProvider('providerSegments')]
    public function testDecodedBytesSurviveBothEscapeModes(array $segments): void
    {
        $literal = new StringLiteral($segments);
        foreach (['', 'NO_BACKSLASH_ESCAPES'] as $mode) {
            $reply = (new Execution())->run($literal, mode: $mode);

            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[implode('', $segments)]], $reply->rows);
        }
    }
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerNulNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Resolves a generated literal or explicit alias through its derived table's semantic name.
     */
    #[DataProvider('providerNulNames')]
    public function testNulTerminatesTheColumnNameWithoutTruncatingTheValue(string $release, int $seed): void
    {
        $choices = new Choices('nul-column-name:' . $seed);
        $prefix = 'n' . str_repeat($choices->one(['a', 'x', '_']), $choices->integer(1, 30));
        $value = $prefix . "\0" . str_repeat($choices->one(['b', '猫', "\0"]), $choices->integer(1, 10));
        $alias = $choices->one([true, false]) ? new \SqlSemantics\Statement\Identifier\Name($value) : null;
        $inner = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new StringLiteral([$value]), $alias)]);
        $outer = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name($prefix)))], new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($inner, new \SqlSemantics\Statement\Identifier\Name('input')));
        $reply = (new Execution())->run($outer, $release);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$value]], $reply->rows);
        self::assertSame($prefix, $reply->columns[0]->name);
    }

}
