<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;

#[CoversClass(Deallocate::class)]
#[Medium]
final class DeallocateTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DEALLOCATE PREPARE s');

        self::assertNull($operation->output());
    }

    public function testRenderPreservesTheDropVerb(): void
    {
        self::assertSame('DROP PREPARE s', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('drop prepare s')->toString());
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
            foreach (['DEALLOCATE PREPARE p', 'DROP PREPARE p'] as $sql) {
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
