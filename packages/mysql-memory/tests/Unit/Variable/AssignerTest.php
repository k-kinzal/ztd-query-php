<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Assigner;
use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Assigner::class)]
#[Small]
final class AssignerTest extends TestCase
{
    public function testAssignSetsTheSessionValue(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $definition = $session->variables->catalog->find('div_precision_increment');

        self::assertNotNull($definition);
        $assigner->assign('div_precision_increment', Scope::Session, 7, Domain::integer());
        self::assertSame([7, 4], [$session->variables->read('div_precision_increment'), $session->variables->globals->value($definition)]);
    }

    public function testAssignSetsTheGlobalValue(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $definition = $session->variables->catalog->find('autocommit');

        self::assertNotNull($definition);
        $assigner->assign('AUTOCOMMIT', Scope::Global, 0, Domain::integer());
        self::assertSame('OFF', $session->variables->globals->value($definition));
    }

    public function testAssignRestoresTheGlobalValueForASessionDefault(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $assigner->assign('div_precision_increment', Scope::Global, 9, Domain::integer());
        $assigner->assign('div_precision_increment', Scope::Session, 2, Domain::integer());
        $assigner->assign('div_precision_increment', Scope::Session, null, null);

        self::assertSame(9, $session->variables->read('div_precision_increment'));
    }

    public function testAssignRestoresTheCompiledDefaultForAGlobalDefault(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $definition = $session->variables->catalog->find('div_precision_increment');

        self::assertNotNull($definition);
        $assigner->assign('div_precision_increment', Scope::Global, 9, Domain::integer());
        $assigner->assign('div_precision_increment', Scope::Global, null, null);
        self::assertSame(4, $session->variables->globals->value($definition));
    }

    public function testAssignRefusesAnUnknownVariable(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $this->expectExceptionMessage("Unknown system variable 'nope'");

        $assigner->assign('nope', Scope::Session, 1, Domain::integer());
    }

    public function testAssignRefusesAReadOnlyVariable(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1238);
        $this->expectExceptionMessage("Variable 'version' is a read only variable");

        $assigner->assign('version', Scope::Global, 'x', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testAssignRefusesTheSessionValueOfAGlobalVariable(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1229);
        $this->expectExceptionMessage("Variable 'max_connections' is a GLOBAL variable and should be set with SET GLOBAL");

        $assigner->assign('max_connections', Scope::Session, 10, Domain::integer());
    }

    public function testAssignRefusesTheGlobalValueOfASessionVariable(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1228);
        $this->expectExceptionMessage("Variable 'timestamp' is a SESSION variable and can't be used with SET GLOBAL");

        $assigner->assign('timestamp', Scope::Global, 1, Domain::integer());
    }

    public function testAssignRefusesTheSessionValueOfAVariableOnlyWrittenGlobally(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1621);
        $this->expectExceptionMessage("SESSION variable 'max_allowed_packet' is read-only. Use SET GLOBAL to assign the value");

        $assigner->assign('max_allowed_packet', Scope::Session, 1024, Domain::integer());
    }

    public function testCheckDispatchesOnTheShapeOfTheVariable(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $text = Domain::string(8, Collation::known('utf8mb4_0900_ai_ci'));
        $boolean = $session->variables->catalog->find('autocommit');
        $integer = $session->variables->catalog->find('div_precision_increment');
        $other = $session->variables->catalog->find('time_zone');

        self::assertNotNull($boolean);
        self::assertNotNull($integer);
        self::assertNotNull($other);
        self::assertSame(['OFF', 12, '+01:00'], [$assigner->check($boolean, 'off', $text), $assigner->check($integer, 12, Domain::integer()), $assigner->check($other, '+01:00', $text)]);
    }

    public function testBooleanTakesOnOffTrueFalseOneAndZero(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $definition = $session->variables->catalog->find('autocommit');
        $text = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertNotNull($definition);
        self::assertSame(
            ['ON', 'OFF', 'ON', 'OFF', 'ON', 'OFF'],
            [
                $assigner->boolean($definition, 1, Domain::integer(), '1'),
                $assigner->boolean($definition, 0, Domain::integer(), '0'),
                $assigner->boolean($definition, 'on', $text, 'on'),
                $assigner->boolean($definition, 'Off', $text, 'Off'),
                $assigner->boolean($definition, 'true', $text, 'true'),
                $assigner->boolean($definition, 'FALSE', $text, 'FALSE'),
            ],
        );
    }

    public function testBooleanRefusesAnotherInteger(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $definition = $session->variables->catalog->find('autocommit');

        self::assertNotNull($definition);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'autocommit' can't be set to the value of '2'");

        $assigner->boolean($definition, 2, Domain::integer(), '2');
    }

    public function testBooleanRefusesADecimal(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1232);
        $this->expectExceptionMessage("Incorrect argument type to variable 'autocommit'");

        $session->query('SET autocommit = 0.5');
    }

    public function testBooleanRefusesAnotherWord(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'autocommit' can't be set to the value of 'yes'");

        $session->query("SET autocommit = 'yes'");
    }

    public function testIntegerClipsAValueToTheBoundsWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $assigner = new Assigner($session->variables, $context);
        $definition = $session->variables->catalog->find('div_precision_increment');

        self::assertNotNull($definition);
        self::assertSame([30, 0], [$assigner->integer($definition, 40, Domain::integer(), '40'), $assigner->integer($definition, -1, Domain::integer(), '-1')]);
        self::assertSame(
            [['Warning', 1292, "Truncated incorrect div_precision_increment value: '40'"], ['Warning', 1292, "Truncated incorrect div_precision_increment value: '-1'"]],
            $session->diagnostics->conditions,
        );
    }

    public function testIntegerRefusesNull(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'div_precision_increment' can't be set to the value of 'NULL'");

        $session->query('SET div_precision_increment = NULL');
    }

    public function testIntegerRefusesAString(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1232);
        $this->expectExceptionMessage("Incorrect argument type to variable 'div_precision_increment'");

        $session->query("SET div_precision_increment = '5'");
    }

    public function testTextNormalizesTheSqlModeAndTheCollationAndCharacterSetNames(): void
    {
        $session = (new Instance())->connect();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $modes = $session->variables->catalog->find('sql_mode');
        $collation = $session->variables->catalog->find('collation_connection');
        $charset = $session->variables->catalog->find('character_set_client');

        self::assertNotNull($modes);
        self::assertNotNull($collation);
        self::assertNotNull($charset);
        self::assertSame(['ANSI_QUOTES', 'utf8mb4_bin', 'latin1'], [$assigner->text($modes, 'ansi_quotes', 'ansi_quotes'), $assigner->text($collation, 'UTF8MB4_BIN', 'UTF8MB4_BIN'), $assigner->text($charset, 'LATIN1', 'LATIN1')]);
    }

    public function testTextRefusesAnUnknownSqlMode(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'sql_mode' can't be set to the value of 'NOPE'");

        $session->query("SET sql_mode = 'NOPE'");
    }

    public function testTextRefusesNull(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'sql_mode' can't be set to the value of 'NULL'");

        $session->query('SET sql_mode = NULL');
    }

    public function testTextRefusesAnUnknownCollation(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: 'nope'");

        $session->query("SET collation_connection = 'nope'");
    }

    public function testTextRefusesAnUnknownCharacterSet(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: 'nope'");

        $session->query("SET character_set_client = 'nope'");
    }
}
