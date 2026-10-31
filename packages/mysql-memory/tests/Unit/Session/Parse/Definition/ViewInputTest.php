<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\ViewInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewInput::class)]
#[Medium]
final class ViewInputTest extends TestCase
{
    public function testErrorRetainsTheDefaultDatabasePrecondition(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('CREATE VIEW v AS SELECT @x');
    }

    public function testErrorReportsAnIntrinsicVariableBeforeOpeningTheTarget(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1351);
        $session->query('ALTER VIEW missing.v AS SELECT @x');
    }

    public function testErrorRefusesAParameterAtSemanticConstruction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1351);
        $session->analyze('CREATE VIEW v AS SELECT ?', true);
    }

    public function testErrorRefusesIntoInTheQueryOfAViewInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1350);
        $this->expectExceptionMessage("View's SELECT contains a 'INTO' clause");

        $session->query('CREATE VIEW v AS SELECT 1 INTO @a UNION SELECT SQL_CACHE 2');
    }
}
