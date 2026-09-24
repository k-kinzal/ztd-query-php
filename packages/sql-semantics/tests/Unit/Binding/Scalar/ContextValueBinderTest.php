<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\ContextValueBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ContextValueBinder::class)]
#[Medium]
final class ContextValueBinderTest extends TestCase
{
    public function testBindClassifiesRequestsWithAndWithoutPrecision(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CURRENT_TIMESTAMP(3), CURRENT_TIME, CURRENT_USER, LOCALTIME(2), CURRENT_DATE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $expressions = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $expressions);
        self::assertSame([ContextValueKind::CurrentTimestamp, ContextValueKind::CurrentTime, ContextValueKind::CurrentUser, ContextValueKind::LocalTime, ContextValueKind::CurrentDate], array_column($expressions, 'request'));
        self::assertSame([3, null, null, 2, null], array_column($expressions, 'precision'));
        self::assertSame(['timestamptz', 'timetz', 'text', 'time', 'date'], array_map(static fn ($expression): string => $expression->type->name, $expressions));
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $expressions[0]->nullability);
        self::assertSame('SELECT CURRENT_TIMESTAMP(3), CURRENT_TIME, CURRENT_USER, LOCALTIME(2), CURRENT_DATE', $statement->toString());
    }

    public function testBindAcceptsMySqlEmptyParenthesesAsNoPrecision(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_TIME(), USER()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $time = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $time);
        self::assertSame(ContextValueKind::CurrentTime, $time->request);
        self::assertNull($time->precision);
        $user = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $user);
        self::assertSame(ContextValueKind::User, $user->request);
        self::assertSame('SELECT CURRENT_TIME, USER()', $statement->toString());
    }

    public function testBindLeavesOrdinaryColumnsToTheResolver(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(current_date_value INT)')))->bind('SELECT current_date_value FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $statement->outputs[0]->expression);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testBindReadsMySqlClockFunctions(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT NOW(3), CURDATE(), CURTIME(), UTC_DATE, UTC_TIME(), UTC_TIMESTAMP(1), SYSDATE(), SYSDATE(2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['datetime', 'datetime'], [$query->outputs[5]->expression->type->name, $query->outputs[6]->expression->type->name]);
        $expected = 'SELECT CURRENT_TIMESTAMP(3), CURRENT_DATE, CURRENT_TIME, UTC_DATE, UTC_TIME, UTC_TIMESTAMP(1), SYSDATE(), SYSDATE(2)';
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['NOW', ContextValueKind::CurrentTimestamp])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CURDATE', ContextValueKind::CurrentDate])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CURTIME', ContextValueKind::CurrentTime])]
    #[\PHPUnit\Framework\Attributes\TestWith(['RAND', null])]
    public function testSynonymReadsMySqlCurrentTimeSpellings(string $name, ?ContextValueKind $kind): void
    {
        self::assertSame($kind, ContextValueBinder::synonym($name));
    }

    /**
     * @return array<string, array{Dialect, list<array{string, string}>, ?ContextValueKind}>
     */
    public static function providerTerminals(): array
    {
        return [
            'identifier' => [Dialect::MySql, [['IDENT', 'current_date']], null],
            'lower case keyword' => [Dialect::PostgreSql, [['CURRENT_DATE', 'current_date']], ContextValueKind::CurrentDate],
            'mysql synonym call' => [Dialect::MySql, [['NOW', 'now'], ['(', '('], [')', ')']], ContextValueKind::CurrentTimestamp],
            'bare mysql synonym' => [Dialect::MySql, [['NOW', 'now']], null],
            'postgresql synonym call' => [Dialect::PostgreSql, [['NOW', 'now'], ['(', '('], [')', ')']], null],
            'empty call' => [Dialect::MySql, [['CURRENT_DATE', 'CURRENT_DATE'], ['(', '('], [')', ')']], ContextValueKind::CurrentDate],
            'doubled closing parenthesis' => [Dialect::MySql, [['CURRENT_DATE', 'CURRENT_DATE'], ['(', '('], [')', ')'], [')', ')']], null],
            'call without opening parenthesis' => [Dialect::MySql, [['CURRENT_DATE', 'CURRENT_DATE'], ['IDENT', 'x'], [')', ')']], null],
            'unknown keyword' => [Dialect::MySql, [['CURDATE', 'curdate']], null],
        ];
    }

    /**
     * @param list<array{string, string}> $terminals
     */
    #[DataProvider('providerTerminals')]
    public function testBindClassifiesTheTerminals(Dialect $dialect, array $terminals, ?ContextValueKind $expected): void
    {
        $node = new Node('func_expr', 0, array_map(static fn (array $terminal): Token => new Token(0, $terminal[0], $terminal[1], 0), $terminals));
        self::assertSame($expected, (new ContextValueBinder())->bind($node, new Scope(new Identifiers($dialect)))?->request);
    }
}
