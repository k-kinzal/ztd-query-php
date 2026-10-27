<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Script;

#[CoversNothing]
#[Small]
final class StatementTest extends TestCase
{
    public function testDialectStatementsExcludeGenericScripts(): void
    {
        self::assertContains(Statement::class, class_implements(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class));
        self::assertNotContains(Statement::class, class_implements(Script::class));
    }

    public function testInitializeStatementInformationAttachesTheSelectedLanguage(): void
    {
        $statement = new \SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart();
        $information = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847));
        $statement->initializeStatementInformation($information);
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql847, $statement->profile()->grammar);
    }

    public function testStatementInformationContainsTheResultOfTheQuery(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 AS n');
        self::assertInstanceOf(Statement::class, $statement);
        self::assertSame($statement->field(0), $statement->statementInformation()->output?->projection[0]);
    }

}
