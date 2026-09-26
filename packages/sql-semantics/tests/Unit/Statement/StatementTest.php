<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class StatementTest extends TestCase
{
    public function testToStringUsesIndependentlyConstructedData(): void
    {
        $transaction = new \SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324();
        $commit = new \SqlSemantics\Statement\Statement(new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('COMMIT', $transaction));
        $end = new \SqlSemantics\Statement\Statement(new \SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149('END', $transaction));
        self::assertSame('COMMIT TRANSACTION', $commit->toString());
        self::assertSame('END TRANSACTION', $end->toString());
        self::assertSame('COMMIT TRANSACTION', $commit->toString());
    }
}
