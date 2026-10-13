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
}
