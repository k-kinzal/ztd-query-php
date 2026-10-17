<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\NoticeHeader;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(NoticeHeader::class)]
#[Medium]
final class NoticeHeaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ProgramKind}>
     */
    public static function providerUnfinishedBodies(): iterable
    {
        yield 'procedure' => ['CREATE DEFINER=CURRENT_USER PROCEDURE db.p() BEGIN ALTER EVENT e; BOGUS END', ProgramKind::Procedure];
        yield 'function' => ['CREATE FUNCTION db.f() RETURNS INT BEGIN ALTER EVENT e; BOGUS END', ProgramKind::Function];
        yield 'trigger' => ['CREATE TRIGGER db.tr BEFORE INSERT ON t FOR EACH ROW BEGIN ALTER EVENT e; BOGUS END', ProgramKind::Trigger];
        yield 'event' => ['CREATE EVENT db.outer_event ON SCHEDULE EVERY 1 DAY DO BEGIN ALTER EVENT e; BOGUS END', ProgramKind::Event];
    }

    #[DataProvider('providerUnfinishedBodies')]
    public function testObserveRetainsTheEnclosingDeclarationBeforeLaterSyntaxFails(string $sql, ProgramKind $kind): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        $notice = $notices[0][1];
        self::assertInstanceOf(ParseFailure::class, $notice);
        self::assertInstanceOf(RefusedProgramBody::class, $notice->problem);
        self::assertSame($kind, $notice->problem->kind);
        self::assertSame('db', $notice->problem->name->schema?->value);
        self::assertInstanceOf(EmptyEventAlteration::class, $notice->problem->problem);
    }

    public function testScopedLeavesStandaloneStatementsUnwrapped(): void
    {
        $notice = new ParseFailure(new EmptyEventAlteration(new QualifiedName(new Name('e'))), true);

        self::assertSame($notice, (new NoticeHeader())->scoped($notice));
    }
    public function testScopedRetainsTheDatabaseScopeForRefusedViewColumns(): void
    {
        $sql = 'CREATE PROCEDURE db.p() BEGIN CREATE VIEW v (``) AS SELECT 1; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        $notice = $notices[0][1];
        self::assertInstanceOf(ParseFailure::class, $notice);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns::class, $notice->problem);
        self::assertSame('db', $notice->problem->program?->schema?->value);
    }

}
