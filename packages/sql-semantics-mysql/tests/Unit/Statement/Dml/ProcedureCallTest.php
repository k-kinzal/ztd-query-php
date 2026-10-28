<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(ProcedureCall::class)]
#[Medium]
final class ProcedureCallTest extends TestCase
{
    public function testDeriveStatementDerivesTheArguments(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CALL p(1, @x)');
        self::assertInstanceOf(ProcedureCall::class, $operation);

        self::assertInstanceOf(Dependent::class, $operation->arguments[1]->scalarInformation()->type);
        self::assertNull($operation->output());
    }

    public function testRenderPreservesEmptyParentheses(): void
    {
        self::assertSame('CALL p()', (new Semantics(Dialect::MySql))->analyze('call p()')->toString());
        self::assertSame('CALL db.p(1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('call db.p (1)')->toString());
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
            foreach (['CALL p', 'CALL p()', 'CALL p(1)'] as $sql) {
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
