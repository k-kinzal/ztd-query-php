<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(BodyConditions::class)]
#[Medium]
final class BodyConditionsTest extends TestCase
{
    #[DataProvider('providerBodies')]
    public function testRefusalIsRaisedByTheReadingBeforeTheDefinitionIsConstructed(string $sql, string $message, bool $aborts): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
            self::fail('The body has a definition condition.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(M\Routine\Problem\RefusedProgramBody::class, $failure->problem);
            self::assertSame($message, $failure->problem->message());
            self::assertSame($aborts, $failure->aborts);
        }
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerBodies(): iterable
    {
        foreach (['CREATE PROCEDURE p() ', 'CREATE FUNCTION f() RETURNS INT ', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ', 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO ', 'ALTER EVENT e DO '] as $header) {
            foreach (['FETCH c INTO x' => 'Undefined CURSOR: c', 'OPEN c' => 'Undefined CURSOR: c', 'CLOSE c' => 'Undefined CURSOR: c', 'SIGNAL missing_condition' => 'Undefined CONDITION: missing_condition'] as $body => $message) {
                yield $header . $body => [$header . $body, $message, true];
            }
        }
        yield 'function without RETURN' => ['CREATE FUNCTION f() RETURNS INT BEGIN END', 'No RETURN found in FUNCTION f', false];
        yield 'ordinary function instruction' => ['CREATE FUNCTION f() RETURNS INT SELECT 1', 'Not allowed to return a result set from a function', false];
        yield 'commit in a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW COMMIT', 'Explicit or implicit commit is not allowed in stored function or trigger.', false];
        yield 'RETURN outside function' => ['CREATE PROCEDURE p() RETURN 1', 'RETURN is only allowed in a FUNCTION', true];
        yield 'generated marker counterexample' => ['CREATE FUNCTION `10`.`2`(`あ` DECIMAL, utf8mb4_0900_ai_ci DECIMAL, `0` DECIMAL) RETURNS MEDIUMBLOB RETURN !! JSON_OBJECTAGG(0, ?)', 'A stored routine or trigger body has no client parameter binding scope.', true];
        yield 'language mismatch' => ["CREATE FUNCTION f() RETURNS INT AS 'body'", 'The routine language and body representation disagree.', true];
    }

    public function testRefusalFindsTheSameConditionInDirectlyConstructedInstructions(): void
    {
        $body = new M\Routine\Program\Cursor\OpenCursor(new Name('c'));
        $refusal = (new BodyConditions())->refusal($body, M\Routine\ProgramKind::Function, new QualifiedName(new Name('f')), new M\Routine\ParameterList([]));
        self::assertNotNull($refusal);
        self::assertSame('Undefined CURSOR: c', $refusal[0]->message());
        self::assertSame($body, $refusal[1]);
        self::assertTrue($refusal[2]);
    }

    public function testRefusalAcceptsAValidBody(): void
    {
        $rule = new BodyConditions();
        self::assertNull($rule->refusal(new M\Routine\Program\Block(), M\Routine\ProgramKind::Procedure, new QualifiedName(new Name('p')), new M\Routine\ParameterList([])));
        self::assertNull($rule->refusal(new M\Routine\Program\Block([], [new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1'))]), M\Routine\ProgramKind::Function, new QualifiedName(new Name('f')), new M\Routine\ParameterList([])));
    }

    public function testCompletedFindsTheBodyWideRestrictionAtTheBody(): void
    {
        $body = new M\Routine\Program\Block([], [new M\Server\Transaction\Commit(), new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1'))]);
        $completed = (new BodyConditions())->completed($body, M\Routine\ProgramKind::Function, new QualifiedName(new Name('f')), new M\Routine\ParameterList([]));
        self::assertNotNull($completed);
        self::assertSame('Explicit or implicit commit is not allowed in stored function or trigger.', $completed[0]->message());
        self::assertSame($body, $completed[1]);
        self::assertNull((new BodyConditions())->completed(new M\Routine\Program\Block(), M\Routine\ProgramKind::Event, new QualifiedName(new Name('e')), null));
    }

    public function testCompletedReportsTheLanguageMismatchBeforeAMissingReturn(): void
    {
        $completed = (new BodyConditions())->completed(new M\Routine\Program\Block(), M\Routine\ProgramKind::Function, new QualifiedName(new Name('f')), new M\Routine\ParameterList([]), [new M\Routine\Characteristic\RoutineLanguage(new Name('JAVASCRIPT'))], 7);
        self::assertNotNull($completed);
        self::assertInstanceOf(M\Routine\Problem\LanguageBodyMismatch::class, $completed[0]);
        self::assertSame(7, $completed[0]->offset);
    }

    /**
     * @return iterable<string, array{M\Routine\Problem\ProgramProblem, bool}>
     */
    public static function providerAborts(): iterable
    {
        yield 'result set' => [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::ResultSet, 'function'), false];
        yield 'missing RETURN' => [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::MissingReturn, 'f'), false];
        yield 'function statement' => [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::FunctionStatement, 'FLUSH'), false];
        yield 'RETURN outside function' => [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::ReturnOutsideFunction), true];
        yield 'bad statement' => [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::BadStatement, 'LOCK'), true];
    }

    #[DataProvider('providerAborts')]
    public function testAbortsStopsReadingAtEveryConditionButBodyWideRestrictions(M\Routine\Problem\ProgramProblem $problem, bool $aborts): void
    {
        self::assertSame($aborts, (new BodyConditions())->aborts($problem));
    }

    public function testOrderPlacesTheBodyNoticesAfterTheDeclarationNotices(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("CREATE FUNCTION f(a INT(5)) RETURNS INT RETURN BINARY 'x'");
        self::assertInstanceOf(M\Routine\CreateFunction::class, $statement);
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-8.4.7'))->context());
        (new BodyConditions())->order($statement, $derivation);
        $messages = array_map(static fn ($notice): string => $notice->warning->message(), $statement->notices());
        self::assertSame(2, count($messages));
        self::assertStringContainsString('display width', $messages[0]);
        self::assertStringContainsString('BINARY', $messages[1]);
    }
}
