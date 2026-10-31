<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $truncate = $semantics->analyze('TRUNCATE t', [$table]);

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $truncate);
        self::assertInstanceOf(DeclaredTable::class, $truncate->relationInformation()->table);
    }

    public function testRenderPreservesAnOmittedTableWord(): void
    {
        self::assertSame('TRUNCATE db.t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('truncate db.t')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: TRUNCATE TABLE reports that the table doesn\'t exist.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('TRUNCATE TABLE v', [$table, $view])->diagnostics()));
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
            foreach (['TRUNCATE t', 'TRUNCATE TABLE t'] as $sql) {
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
