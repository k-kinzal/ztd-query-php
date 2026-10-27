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
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;

/**
 * Executes generated alterations together with their table and row dependencies.
 */
#[CoversNothing]
#[Large]
final class AlterTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Dependent generated statements retain rows and the completion expected for their engine.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesIndexChangesPreserveRowsAndReportEngineCopies(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::indexSequences($release))->then(/** @param array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int} $sequence */
            static function (array $sequence) use ($release): void {
                [$create, $insert, $alter, $affected] = $sequence;
                self::assertInstanceOf(CreateTable::class, $create);
                self::assertInstanceOf(InsertRows::class, $insert);
                self::assertInstanceOf(AlterTable::class, $alter);
                $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
                $session->text = 'Generated index change without SQL';
                $execution = new Execution($session);
                $execution->statement($create);
                $execution->statement($insert);
                $reply = $execution->statement($alter);
                self::assertInstanceOf(Completion::class, $reply);
                self::assertSame($affected, $reply->affectedRows);
                self::assertSame('Records: ' . $reply->affectedRows . '  Duplicates: 0  Warnings: 0', $reply->info);
                self::assertSame(0, $reply->warnings);
                $table = $session->instance->dictionary->table('pbt', $create->name->name->value, $session->temporaries);
                self::assertNotNull($table);
                $expected = [];
                foreach ($insert->rows as $row) {
                    $values = [];
                    foreach ($row->values as $value) {
                        self::assertInstanceOf(StringLiteral::class, $value);
                        $values[] = (int) $value->value();
                    }
                    $expected[] = $values;
                }
                self::assertSame($expected, array_values($table->data->rows));
                self::assertSame('Generated index change without SQL', $session->text);
            }
        );
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
