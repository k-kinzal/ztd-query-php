<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Eris\Quantifier\ForAll;
use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use MySqlMemory\Command\Show\ShowCreateTableCommand;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Eris\ShrinkerFactory;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;

#[CoversNothing]
#[Large]
final class TableOptionShrinkingTest extends TestCase
{
    public function testShrinkingPreservesOptionBoundariesAndReducesTheExecutedComment(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1907);
        $quantifier = new ForAll([CreateTableGenerator::optionDefinitions(GrammarRelease::MySql847)], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional option counterexample');
        try {
            $quantifier->then(static function (array $sample) use (&$attempts, &$last): void {
                [, $statement] = $sample;
                self::assertInstanceOf(CreateTable::class, $statement);
                self::assertCount(count($statement->options) - 1, $statement->optionsCommas);
                $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
                (new Execution($session))->statement($statement);
                $definition = $session->instance->dictionary->schema('pbt')?->table($statement->name->name->value)?->definition;
                self::assertNotNull($definition);
                $last = (new ShowCreateTableCommand())->comment($definition);
                $attempts++;
                self::fail('Intentional option counterexample');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame('comment 0', $last);
        }
    }
}
