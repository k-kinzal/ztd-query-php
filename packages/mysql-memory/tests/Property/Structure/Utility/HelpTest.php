<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Utility;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;

/**
 * The semantic HELP operand alone drives searches over explicit server data.
 */
#[CoversNothing]
#[Medium]
final class HelpTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Constructor-generated searches remain executable with unrelated source text and changing data.
     *
     * @throws JsonException When the semantic inventory is invalid
     * @throws ReflectionException When constructor types cannot be inspected
     */
    #[DataProvider('providerProfiles')]
    public function testConstructedSearchUsesTheSemanticName(string $release, int $seed): void
    {
        $choices = new Choices('help-structure:' . $seed);
        $statement = (new Construction($choices, new Catalogue(), new Metadata()))->object(Help::class, 4);
        self::assertInstanceOf(Help::class, $statement);
        $name = $statement->topic->value;
        $text = 'fixture:' . $choices->integer(0, 100000);
        $empty = (new Instance($release, globals: ['character_set_results' => 'utf8mb4']))->connect();
        $full = (new Instance($release, globals: ['character_set_results' => 'utf8mb4'], help: ['topics' => [['1', $name, '1', $text, 'Example']]]))->connect();
        $empty->text = $full->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $missing = $execution->runIn($empty, $statement);
        $found = $execution->runIn($full, $statement);

        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertInstanceOf(ResultSet::class, $found);
        self::assertSame([], $missing->rows);
        self::assertSame([[$name, $text, 'Example']], $found->rows);
        self::assertSame(['name', 'description', 'example'], array_column($found->columns, 'name'));
    }
}
