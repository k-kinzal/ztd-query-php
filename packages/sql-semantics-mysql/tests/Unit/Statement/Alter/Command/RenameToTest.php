<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;

#[CoversClass(RenameTo::class)]
#[Medium]
final class RenameToTest extends TestCase
{
    public function testDeriveCommandReportsANameAnotherTableHas(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $other = $semantics->analyze('CREATE TABLE u (a INT)');

        self::assertSame('Table u already exists.', $semantics->analyze('ALTER TABLE t RENAME u', [$table, $other])->diagnostics()[0]->message());
        self::assertSame([], $semantics->analyze('ALTER TABLE t RENAME TO t', [$table, $other])->diagnostics());
    }

    public function testRenderPreservesTheEqualsConnector(): void
    {
        self::assertSame('ALTER TABLE t RENAME = db.u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t RENAME = db.u')->toString());
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, string}>
     */
    public static function providerWrittenForms(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['ALTER TABLE t RENAME u', 'ALTER TABLE t RENAME TO u', 'ALTER TABLE t RENAME AS u', 'ALTER TABLE t RENAME = u'] as $sql) {
                yield "$release->value/statement/$sql" => [$release, $sql];
                yield "$release->value/program/$sql" => [$release, 'CREATE PROCEDURE wrapper() ' . $sql];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerWrittenForms')]
    public function testRenderPreservesWrittenFormsThroughPublication(\SqlSemantics\Contract\GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

}
