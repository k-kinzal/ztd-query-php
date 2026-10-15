<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RoutineDeclarationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement|Scalar>}>
     */
    public static function providerStructures(): iterable
    {
        $utf8 = new CharsetAttribute(CharsetForm::CharacterSet, new Name('utf8mb4'));
        $types = [
            [new Character(CharacterKind::VarChar, '3', charset: $utf8), 'utf8mb4_bin'],
            [new Character(CharacterKind::Char, '3', charset: new CharsetAttribute(CharsetForm::Ascii)), 'latin1_bin'],
            [new Character(CharacterKind::Char, '3', charset: new CharsetAttribute(CharsetForm::Unicode)), 'ucs2_bin'],
            [new Character(CharacterKind::Char, '3', charset: new CharsetAttribute(CharsetForm::Byte)), 'binary'],
            [new Binary(BinaryKind::VarBinary, '3'), 'binary'],
            [new Enumeration(EnumerationKind::Enum, [new Text('x')], $utf8), 'utf8mb4_bin'],
        ];
        $nodes = [];
        $value = new StringLiteral(['x']);
        $select = new QueryRoot(new Select([], [new SelectExpression(new KeywordCall(KeywordFunction::Collation, [new ColumnUse(new Name('x'))]), new Name('c'))]));
        foreach ($types as $index => [$type, $name]) {
            $collation = new CollationName(new Name($name));
            $procedure = new QualifiedName(new Name('p' . $index));
            $local = new QualifiedName(new Name('l' . $index));
            $function = new QualifiedName(new Name('f' . $index));
            $nodes[] = new CreateProcedure($procedure, new ParameterList([new Parameter(new Name('x'), $type, $collation)]), $select);
            $nodes[] = new ProcedureCall($procedure, [$value]);
            $nodes[] = new ProcedureCall($procedure, [$value]);
            $nodes[] = new CreateProcedure($local, new ParameterList([]), new Block([new VariableDeclaration([new Name('x')], $type, $collation, $value)], [$select]));
            $nodes[] = new ProcedureCall($local);
            $nodes[] = new ProcedureCall($local);
            $nodes[] = new CreateFunction($function, new ParameterList([]), $type, new ReturnStatement($value), $collation);
            $nodes[] = new KeywordCall(KeywordFunction::Collation, [new FunctionCall($function->name, schema: new Name('d'))]);
            $nodes[] = new KeywordCall(KeywordFunction::Collation, [new FunctionCall($function->name, schema: new Name('d'))]);
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<Statement|Scalar> $nodes Declarations and calls constructed without parsing SQL
     */
    #[DataProvider('providerStructures')]
    public function testDirectDeclarationsCarryTheirCollationIntoRoutineExecution(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $other = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $instance = new Instance($release);
                $session = $instance->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', 'CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', 'USE d', 'SET GLOBAL log_bin_trust_function_creators=1'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $profile = $session->semantics()->profile();
                $expected = array_map(static fn (Statement|Scalar $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                $session->text = 'unrelated invalid input';
                $actual = array_map(static function (Statement|Scalar $node) use ($session): array {
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                }, $nodes);

                self::assertSame($expected, array_column($actual, 0));
                self::assertSame(array_fill(0, count($nodes), null), array_column($actual, 1));
                self::assertSame('unrelated invalid input', $session->text);
                $second = $instance->connect();
                $setup = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', 'USE d'];
                array_map(static fn (string $sql): bool|mysqli_result => $other->query($sql), $setup);
                array_map($second->run(...), $setup);
                $calls = array_values(array_filter($nodes, static fn (Statement|Scalar $node): bool => $node instanceof ProcedureCall || $node instanceof KeywordCall));
                $second->text = 'unrelated invalid input';
                self::assertSame(array_map(static fn (Statement|Scalar $node): string => serialize((new Native())->observe($other, (new Reference())->sql($node, $profile))), $calls), array_map(static fn (Statement|Scalar $node): string => serialize((new Memory())->observe($second, $node)), $calls));
                self::assertSame('unrelated invalid input', $second->text);
            } finally {
                $native->close();
                $other->close();
            }
        } finally {
            $container->stop();
        }
    }
}
