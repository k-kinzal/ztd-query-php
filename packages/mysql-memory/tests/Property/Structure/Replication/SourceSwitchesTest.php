<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Replication;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * The switch subdomain of constructor-generated source options executes with its state prerequisites.
 * Selection depends only on the option kind, never on execution or diagnostic outcomes.
 */
#[CoversNothing]
#[Medium]
final class SourceSwitchesTest extends TestCase
{
    /**
     * @return iterable<string, array{SourceOption}>
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When constructor types cannot be inspected
     * @throws RuntimeException When the finite sample does not contain enough switches
     */
    public static function providerOptions(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $found = 0;
        for ($seed = 0; $seed < 10000 && $found < 100; $seed++) {
            $value = (new Construction(new Choices('source-switch:' . $seed), $catalogue, $metadata))->object(SourceOption::class, 4);
            if ($value instanceof SourceOption && in_array($value->kind, [SourceOptionKind::ConnectionAutoFailover, SourceOptionKind::GtidOnly, SourceOptionKind::RequireRowFormat], true)) {
                $found++;
                yield 'source-switch:' . $seed => [$value];
            }
        }
        if ($found !== 100) {
            throw new RuntimeException('The finite constructor sampling budget did not produce 100 source switches.');
        }
    }

    /**
     * Executes an accepted switch against a channel with GTIDs, auto-positioning and row events.
     */
    #[DataProvider('providerOptions')]
    public function testGeneratedSwitchExecutesWithItsChannelPrerequisites(SourceOption $option): void
    {
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON']))->connect();
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $execution->runIn($session, new ChangeReplicationSource(Terminology::Current, [new SourceOption(Terminology::Current, SourceOptionKind::AutoPosition, new Numeral('1')), new SourceOption(Terminology::Current, SourceOptionKind::RequireRowFormat, new Numeral('1'))]));

        self::assertInstanceOf(Completion::class, $execution->runIn($session, new ChangeReplicationSource(Terminology::Current, [$option])));
        self::assertSame([], $session->diagnostics->conditions);
    }
}
