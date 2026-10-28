<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
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
    public function testPublishRetainsTheCompleteRequestAndItsFirstCondition(string $sql, string $message): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
        $statement = $semantics->analyze($sql);
        $formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
        self::assertInstanceOf(M\Statement::class, $statement);
        $failures = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof M\Notice\ParseFailure));
        self::assertNotEmpty($failures);
        self::assertSame($message, $failures[0]->message());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBodies(): iterable
    {
        foreach (['CREATE PROCEDURE p() ', 'CREATE FUNCTION f() RETURNS INT ', 'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW ', 'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO ', 'ALTER EVENT e DO '] as $header) {
            foreach (['FETCH c INTO x' => 'Undefined CURSOR: c', 'OPEN c' => 'Undefined CURSOR: c', 'CLOSE c' => 'Undefined CURSOR: c', 'SIGNAL missing_condition' => 'Undefined CONDITION: missing_condition', 'BEGIN SIGNAL missing_condition; SELECT 1 FROM a,a; END' => 'Undefined CONDITION: missing_condition', 'BEGIN SELECT 1 FROM a,a; SIGNAL missing_condition; END' => "Not unique table/alias: 'a'."] as $body => $message) {
                yield $header . $body => [$header . $body, $message];
            }
        }
        yield 'function without RETURN' => ['CREATE FUNCTION f() RETURNS INT BEGIN END', 'No RETURN found in FUNCTION f'];
        yield 'ordinary function instruction' => ['CREATE FUNCTION f() RETURNS INT SELECT 1', 'Not allowed to return a result set from a function'];
        yield 'RETURN outside function' => ['CREATE PROCEDURE p() RETURN 1', 'RETURN is only allowed in a FUNCTION'];
        yield 'generated marker counterexample' => ['CREATE FUNCTION `10`.`2`(`あ` DECIMAL, utf8mb4_0900_ai_ci DECIMAL, `0` DECIMAL) RETURNS MEDIUMBLOB RETURN !! JSON_OBJECTAGG(0, ?)', 'A stored routine or trigger body has no client parameter binding scope.'];
        yield 'language mismatch' => ["CREATE FUNCTION f() RETURNS INT AS 'body'", 'The routine language and body representation disagree.'];
    }

    public function testFirstDerivesTheSameConditionFromDirectlyConstructedFunctionInstructions(): void
    {
        $statement = new M\Routine\CreateFunction(new QualifiedName(new Name('f')), new M\Routine\ParameterList([]), new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation(new M\Routine\Program\Cursor\OpenCursor(new Name('c'))));
        $condition = (new BodyConditions())->first($statement, new Derivation((new Semantics(Dialect::MySql))->context()));
        self::assertNotNull($condition);
        self::assertSame('Undefined CURSOR: c', $condition[0]->message());
        self::assertSame($statement->body, $condition[1]);
        self::assertTrue($condition[2]);
        self::assertSame('CREATE FUNCTION f() RETURNS INT OPEN c', Semantics::fromStatement((new Semantics(Dialect::MySql))->context(), $statement)->toString());
    }

    public function testCompletedAcceptsAValidBodyAndAnAlterationWithoutABody(): void
    {
        $name = new QualifiedName(new Name('p'));
        $parameters = new M\Routine\ParameterList([]);
        $procedure = new M\Routine\CreateProcedure($name, $parameters, new M\Routine\Program\Block());
        $event = new M\Routine\AlterEvent($name, status: M\Routine\Event\EventStatus::Enable);
        $rule = new BodyConditions();
        self::assertNull($rule->completed($procedure, M\Routine\ProgramKind::Procedure, $parameters));
        self::assertNull($rule->completed($event, M\Routine\ProgramKind::Event, null));
        self::assertNull($rule->first($event, new Derivation((new Semantics(Dialect::MySql))->context())));
    }
}
