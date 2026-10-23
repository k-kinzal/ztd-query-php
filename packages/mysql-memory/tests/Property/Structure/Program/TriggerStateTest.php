<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Result\Observation;

/**
 * Exercises unchanged trigger definitions across missing and populated schema states.
 */
#[CoversNothing]
#[Medium]
final class TriggerStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, TriggerTime, TriggerEvent}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (TriggerTime::cases() as $time) {
                foreach (TriggerEvent::cases() as $event) {
                    yield $release . ':' . $time->value . ':' . $event->value => [$release, $time, $event];
                }
            }
        }
    }

    /**
     * Preserves the semantic definition while its target and duplicate conditions change.
     */
    #[DataProvider('providerHistories')]
    public function testOneDefinitionSurvivesTheDatabaseAndPopulatedTableTransitions(string $release, TriggerTime $time, TriggerEvent $event): void
    {
        $create = new CreateTrigger(new QualifiedName(new Name('tr'), new Name('x')), $time, $event, new TriggerTable(new QualifiedName(new Name('t'))), new Block([], []));
        $session = (new Instance($release))->connect();
        $memory = new Observation();
        $session->text = 'unrelated invalid input';
        $absent = $memory->observe($session, $create);
        self::assertSame(str_starts_with($release, '5.') ? 1146 : 1049, $absent['error'][0] ?? null);
        $session->query('CREATE DATABASE x');
        $session->text = 'unrelated invalid input';
        $missing = $memory->observe($session, $create);
        self::assertSame(1146, $missing['error'][0] ?? null);
        $session->query('CREATE TABLE x.t(a INT); INSERT INTO x.t VALUES(1),(2)');
        $session->text = 'unrelated invalid input';
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $duplicate = $memory->observe($session, $create);
        self::assertSame($release === '5.6.51' ? 1235 : 1359, $duplicate['error'][0] ?? null);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
