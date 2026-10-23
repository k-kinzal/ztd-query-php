<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Utility;

use JsonException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Constructor-generated connection operands keep their meaning independently of input spelling.
 */
#[CoversNothing]
#[Medium]
final class ExplainConnectionProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, Numeral}>
     * @throws JsonException When the reviewed inventory is invalid
     * @throws RuntimeException When the bounded sample contains too few positive thread identities
     */
    public static function providerIdentifiers(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $count = 0;
        for ($seed = 0; $seed < 1000 && $count < 25; $seed++) {
            $value = (new Construction(new Choices('explain-identifier:' . $seed), $catalogue, $metadata))->object(ExplainConnection::class, 4);
            if ($value instanceof ExplainConnection && $value->connectionId > 0) {
                $count++;
                foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
                    yield $release . ':' . $seed => [$release, $value->connection];
                }
            }
        }
        if ($count !== 25) {
            throw new RuntimeException('The bounded sample did not produce 25 positive connection identifiers.');
        }
    }

    /**
     * The same constructed operand fails without its connection and completes once that state exists.
     */
    #[DataProvider('providerIdentifiers')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedIdentifierResolvesTheExistingConnection(string $release, Numeral $number): void
    {
        $statement = new ExplainConnection($number);
        $instance = new Instance($release);
        $session = $instance->connect(threadId: $statement->connectionId === 1 ? 2 : 1);
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $failure = null;
        try {
            $execution->runIn($session, $statement);
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(1094, $failure->getCode());
        self::assertSame('Unknown thread id: ' . $statement->connectionId, $failure->getMessage());
        $target = $instance->connect(threadId: $statement->connectionId);

        self::assertInstanceOf(Completion::class, $execution->runIn($session, $statement));
        self::assertSame($statement->connectionId, $target->id);
    }
}
