<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\StatementException;
use SqlSemantics\Statement\Syntax;

#[CoversClass(Syntax::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Statement::class)]
#[UsesClass(StatementException::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Equality::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[Medium]
final class SyntaxTest extends TestCase
{
    public function testVerifyOfTheLanguageOfAnAnalyzedStatementAcceptsIt(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('SELECT 1');
        self::assertSame($semantics->language(), $statement->syntax);
        $statement->syntax->verify($statement);
        self::assertSame('SELECT 1', $statement->toString());
    }

    public function testVerifyDecidesWhetherAStatementCanBeBuilt(): void
    {
        $command = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1')->command;
        $refusing = new class () implements Syntax {
            public function verify(Statement $statement): void
            {
                throw new StatementException('Refused: ' . $statement->toString());
            }
        };
        $this->expectException(StatementException::class);
        $this->expectExceptionMessage('Refused: SELECT 1');
        new Statement($refusing, $command);
    }
}
