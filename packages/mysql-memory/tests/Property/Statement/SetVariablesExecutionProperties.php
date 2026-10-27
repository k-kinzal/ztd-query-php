<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Generator\MySql\Statement\Utility\Set\SetCharacterSetGenerator;

/**
 * Executes generated connection assignments without parsing or rendering SQL.
 */
#[CoversNothing]
#[Large]
final class SetVariablesExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Each generated spelling assigns the client and results and restores the database encoding.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedCharsetAssignmentsSetTheConnectionEncodings(GrammarRelease $release): void
    {
        $this->forAll(SetCharacterSetGenerator::assignments($release))->then(static function (AnalyzedStatement $statement) use ($release): void {
            self::assertInstanceOf(SetVariables::class, $statement);
            $assignment = $statement->items[0];
            self::assertInstanceOf(SetCharacterSet::class, $assignment);
            self::assertNotNull($assignment->charset->name);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $database = $session->variables->read('character_set_database');
            $session->text = 'Generated connection charset without SQL';
            self::assertInstanceOf(Completion::class, (new Execution($session))->statement($statement));
            self::assertSame($assignment->charset->name->value, $session->variables->read('character_set_client'));
            self::assertSame($assignment->charset->name->value, $session->variables->read('character_set_results'));
            self::assertSame($database, $session->variables->read('character_set_connection'));
            self::assertSame('Generated connection charset without SQL', $session->text);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
