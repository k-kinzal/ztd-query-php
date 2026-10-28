<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AlterEvent::class)]
#[Medium]
final class AlterEventTest extends TestCase
{
    public function testDeriveStatementResolvesTheLocalVariablesOfTheBody(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER EVENT e ON SCHEDULE EVERY 1 HOUR DO BEGIN DECLARE v INT DEFAULT 1; UPDATE t SET b = v WHERE a = v; END', [$table]);

        self::assertSame([], $alter->diagnostics());
    }

    public function testDeriveStatementReportsUnknownNamesOfTheScheduleAndTheBody(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER EVENT e ON SCHEDULE AT a DO DELETE FROM t WHERE zz = 1', [$table]);

        self::assertSame(['Column a does not exist.', 'Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->diagnostics()));
    }

    public function testDeriveStatementRejectsAnAlterationWithoutClauses(): void
    {
        $this->expectExceptionMessage('ALTER EVENT changes at least one event property after its name.');

        (new Semantics(Dialect::MySql))->analyze('ALTER EVENT e');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveStatementReportsAStatementAStoredProgramMayNotContain(): iterable
    {
        yield 'LOCK TABLES in a block' => ['BEGIN DECLARE v INT; SET @x = v; LOCK TABLES t READ; END', 'LOCK is not allowed in stored procedures'];
    }

    #[DataProvider('providerDeriveStatementReportsAStatementAStoredProgramMayNotContain')]
    public function testDeriveStatementReportsAStatementAStoredProgramMayNotContain(string $body, string $message): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('ALTER EVENT e DO ' . $body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($message, $notices[0]->message());
        self::assertNotSame('', $statement->toString());

    }

    public function testDeriveStatementAcceptsANestedAlterationWithoutABody(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER EVENT e DO ALTER EVENT f ENABLE')->diagnostics());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheChanges(): iterable
    {
        yield 'MySQL 5.6 status only' => ['mysql-5.6.51', 'alter event e disable on slave', 'ALTER EVENT e DISABLE ON SLAVE'];
        yield 'MySQL 5.7 rename' => ['mysql-5.7.44', 'alter event e rename to shop.f enable', 'ALTER EVENT e RENAME TO shop.f ENABLE'];
        yield 'MySQL 8.0 every clause' => [
            'mysql-8.0.44',
            "alter definer = current_user event shop.e on schedule at '2030-01-01' + interval 1 day on completion not preserve rename to shop.f enable comment 'n' do update t set a = 1",
            "ALTER DEFINER = CURRENT_USER EVENT shop.e ON SCHEDULE AT '2030-01-01' + INTERVAL 1 DAY ON COMPLETION NOT PRESERVE RENAME TO shop.f ENABLE COMMENT 'n' DO UPDATE t SET a = 1",
        ];
        yield 'MySQL 8.4 DISABLE ON REPLICA' => ['mysql-8.4.7', 'alter event e disable on replica', 'ALTER EVENT e DISABLE ON REPLICA'];
        yield 'MySQL 9.1 definer and status' => ['mysql-9.1.0', 'alter definer = current_user event e enable', 'ALTER DEFINER = CURRENT_USER EVENT e ENABLE'];
    }

    #[DataProvider('providerRenderWritesTheChanges')]
    public function testRenderWritesTheChanges(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedAlteration(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec($semantics->profile()->grammar));
        (new AlterEvent(new QualifiedName(new Name('e')), null, null, null, EventStatus::Disable))->render($out);

        self::assertSame('ALTER EVENT e DISABLE', (new Lexical())->join($out->pieces()));
    }

    public function testANewNameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('An event name has at most a database qualifier.');

        new AlterEvent(new QualifiedName(new Name('e')), null, null, new QualifiedName(new Name('f'), new Name('shop'), new Name('def')));
    }

    public function testAHexadecimalCommentIsRejected(): void
    {
        $this->expectExceptionMessage('A comment is written as a quoted string.');

        new AlterEvent(new QualifiedName(new Name('e')), null, null, null, null, new Text('41', Radix::Hexadecimal));
    }

    public function testABodyOfAnotherClassIsRejected(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        new AlterEvent(new QualifiedName(new Name('e')), null, null, null, null, null, new Parameter(new Name('x'), new Integral(IntegralKind::Int)));
    }

    public function testDeriveStatementWarnsOfDisableOnSlave(): void
    {
        $facts = (new Semantics(Dialect::MySql))->analyze('ALTER EVENT e DISABLE ON SLAVE');

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DisableOnSlave->value], array_map(static fn ($warning): string => $warning->message(), $facts->warnings()));
    }
    public function testAnEmptyChangeIsRejectedEvenWithADefiner(): void
    {
        $this->expectExceptionMessage('ALTER EVENT changes at least one event property after its name.');

        new AlterEvent(new QualifiedName(new Name('e')), definer: new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser());
    }

    public function testAnEmptyInputKeepsOnlyItsTypedHeaderRefusal(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('ALTER DEFINER=CURRENT_USER EVENT db.e');
            self::fail('An empty event alteration must not be published.');
        } catch (\SqlSemantics\Construction\RejectedInput $error) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration::class, $error->problem);
            self::assertSame('db', $error->problem->name->schema?->value);
        }
    }

    #[DataProvider('providerNestedDeclarationRefusals')]
    public function testNestedDeclarationsRetainTheirInputRefusal(string $body, string $message): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage($message);
        (new Semantics(Dialect::MySql))->analyze('ALTER EVENT e DO ' . $body);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNestedDeclarationRefusals(): iterable
    {
        yield 'ALTER EVENT with a body' => ['ALTER EVENT f DO SELECT 1', 'Recursion of EVENT DDL statements is forbidden when body is present'];
    }

}
