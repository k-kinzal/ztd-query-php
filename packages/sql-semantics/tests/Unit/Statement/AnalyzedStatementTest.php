<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\AnalyzedStatement;

#[CoversClass(AnalyzedStatement::class)]
#[Medium]
final class AnalyzedStatementTest extends TestCase
{
    public function testToStringReconstructsTheConcreteStatement(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT t VALUE ('42')");
        self::assertSame("INSERT t VALUE ('42')", $statement->toString());
    }

    public function testProfileIdentifiesTheSelectedLanguage(): void
    {
        $statement = (new Semantics(Dialect::MySql, '5.6.51'))->analyze("INSERT t VALUE ('42')");
        self::assertSame(GrammarRelease::MySql5651, $statement->profile()->grammar);
    }

    public function testDeclarationsIsEmptyForAWrite(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("INSERT t VALUE ('42')");
        self::assertSame([], $statement->declarations());
    }
}
