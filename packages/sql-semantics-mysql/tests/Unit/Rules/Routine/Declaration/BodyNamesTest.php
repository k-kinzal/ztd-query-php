<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyNames;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(BodyNames::class)]
#[Medium]
final class BodyNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerBodies(): iterable
    {
        yield 'a system variable' => ["CREATE PROCEDURE p() SET sql_mode = ''", null];
        yield 'a parameter' => ['CREATE PROCEDURE p(x INT) SET x = 1', null];
        yield 'a declared variable in any letter case' => ['CREATE PROCEDURE p() BEGIN DECLARE y INT; SET Y = 1; END', null];
        yield 'a variable of an enclosing block' => ['CREATE PROCEDURE p() BEGIN DECLARE y INT; BEGIN SET y = 1; END; END', null];
        yield 'a handler reading the declarations before it' => ['CREATE PROCEDURE p() BEGIN DECLARE y INT; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET y = 1; END', null];
        yield 'an unknown name' => ['CREATE PROCEDURE p() SET x = 1', 'x'];
        yield 'a variable of a closed block' => ['CREATE PROCEDURE p() BEGIN BEGIN DECLARE y INT; END; SET y = 2; END', 'y'];
        yield 'the second item' => ['CREATE PROCEDURE p() BEGIN DECLARE y INT; SET y = 1, z = 2; END', 'z'];
        yield 'a nested statement of a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN IF 1 THEN SET zz = 1; END IF; END', 'zz'];
        yield 'an event body' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SET zz = 1', 'zz'];
    }

    #[DataProvider('providerBodies')]
    public function testUnknownIsRaisedByTheReadingAsAnUnknownSystemVariable(string $sql, ?string $name): void
    {
        try {
            $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
            self::assertNull($name);
            self::assertNotSame('', $statement->toString());
        } catch (RejectedInput $failure) {
            self::assertNotNull($name);
            self::assertInstanceOf(M\Variable\Problem\UnknownSystemVariable::class, $failure->problem);
            self::assertSame($name, $failure->problem->name);
            self::assertTrue($failure->aborts);
        }
    }

    public function testUnknownNamesTheSetStatementAndSkipsLocalsOfTheRelease(): void
    {
        $catalog = SystemVariables::of(GrammarRelease::MySql847);
        $set = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('zz'), new M\Literal\NumberLiteral('1'))]);
        $body = new M\Routine\Program\Block([new M\Routine\Program\VariableDeclaration([new Name('y')], new M\Type\Integral(M\Type\Kind\IntegralKind::Int))], [new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('Y'), new M\Literal\NumberLiteral('1'))]), $set]);
        $unknown = (new BodyNames())->unknown($body, [], $catalog);
        self::assertNotNull($unknown);
        self::assertSame('zz', $unknown[0]->value);
        self::assertSame($set, $unknown[1]);
        self::assertNull((new BodyNames())->unknown($body, [new Name('ZZ')], $catalog));
    }
}
