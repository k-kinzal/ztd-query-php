<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Utility;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Known format names preserve their meaning across generated casing and query values.
 */
#[CoversNothing]
#[Medium]
final class ExplainFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerFormats(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['TRADITIONAL', 'JSON', ...(str_starts_with($release, '5.') ? [] : ['TREE'])] as $format) {
                for ($seed = 0; $seed < 10; $seed++) {
                    yield $release . ':' . $format . ':' . $seed => [$release, $format, $seed];
                }
            }
        }
    }

    /**
     * Constructed formats explain a generated scalar and identify an existing idle connection.
     * Neither operation consumes the unrelated SQL text attached to its session.
     */
    #[DataProvider('providerFormats')]
    public function testConstructedFormatsRemainExecutable(string $release, string $format, int $seed): void
    {
        $choices = new Choices('explain-format:' . $seed);
        $name = implode('', array_map(static fn (string $letter): string => $choices->one([$letter, strtolower($letter)]), str_split($format)));
        $value = new NumberLiteral((string) $choices->integer(0, 100000));
        $statement = new Explain(new QueryRoot(new Select([], [new SelectExpression($value)])), new Name($name));
        $instance = new Instance($release);
        $session = $instance->connect();
        $target = $instance->connect(threadId: 255);
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $reply = $execution->runIn($session, $statement);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount($format === 'TRADITIONAL' ? 12 : 1, $reply->columns);
        self::assertInstanceOf(Completion::class, $execution->runIn($session, new ExplainConnection(new Numeral((string) $target->id), new Name($name))));
    }
}
