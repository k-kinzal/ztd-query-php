<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\SpatialReferenceCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;

#[CoversClass(SpatialReferenceCommand::class)]
#[Small]
final class SpatialReferenceCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new SpatialReferenceCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAndDropsASystem(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("CREATE SPATIAL REFERENCE SYSTEM 1000000201 NAME 'a' DEFINITION '" . 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]' . "'");
        $created = $instance->registry->spatialCatalog->name(1000000201);
        $reply = $session->query('DROP SPATIAL REFERENCE SYSTEM 1000000201')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(['a', null, 0], [$created, $instance->registry->spatialCatalog->name(1000000201), $reply->warnings]);
    }

    public function testExecuteRefusesAnSridInUse(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3712);
        $this->expectExceptionMessage('There is already a spatial reference system with SRID 4326.');

        $session->query("CREATE SPATIAL REFERENCE SYSTEM 4326 NAME 'x' DEFINITION 'x'");
    }

    public function testExecuteWarnsOfAnSridInUseUnderIfNotExists(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4326 NAME 'x' DEFINITION 'x'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3713', 'There is already a spatial reference system with SRID 4326.']], $warnings->rows);
    }

    public function testExecuteRefusesADefinitionItCannotParse(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3517);
        $this->expectExceptionMessage("Can't parse the spatial reference system definition of SRID 1000000099.");

        $session->query("CREATE SPATIAL REFERENCE SYSTEM 1000000099 NAME 'WGS 84' DEFINITION 'x'");
    }

    public function testExecuteRefusesTheNameOfAnotherSystem(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '1-wgs 84' for key 'st_spatial_reference_systems.SRS_NAME'");

        $session->query("CREATE SPATIAL REFERENCE SYSTEM 1000000099 NAME 'wgs 84' DEFINITION '" . 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]' . "'");
    }

    public function testExecuteReplacesASystemUnderOrReplace(): void
    {
        $instance = new Instance();
        $instance->connect()->query("CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 30000 NAME 'mine' DEFINITION '" . 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]' . "'");

        self::assertSame('mine', $instance->registry->spatialCatalog->name(30000));
    }

    public function testExecuteRefusesAnSridWithoutSystem(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3548);
        $this->expectExceptionMessage("There's no spatial reference system with SRID 2147483648.");

        $session->query('DROP SPATIAL REFERENCE SYSTEM 2147483648');
    }

    public function testExecuteWarnsOfAnSridWithoutSystemUnderIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query("DROP SPATIAL REFERENCE SYSTEM IF EXISTS X'0f'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3519', "There's no spatial reference system with SRID 15."]], $warnings->rows);
    }

    public function testExecuteRefusesSrid0(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3714);
        $this->expectExceptionMessage('SRID 0 is not modifiable.');

        $session->query('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 0');
    }

    public function testExecuteRefusesAMissingNameBeforeAnOrganizationOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3708);
        $this->expectExceptionMessage('Missing mandatory attribute NAME.');

        $session->query("CREATE SPATIAL REFERENCE SYSTEM 2 ORGANIZATION 'a''b' IDENTIFIED BY 04111845221216");
    }

    public function testCreateDefinesTheSystemUnderItsName(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $statement = $session->analyze("CREATE SPATIAL REFERENCE SYSTEM 1000000202 NAME 'b' DEFINITION '" . 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]' . "'")->statement;
        self::assertInstanceOf(CreateSpatialReference::class, $statement);

        (new SpatialReferenceCommand())->create($statement, $session);

        self::assertSame(['b', []], [$instance->registry->spatialCatalog->name(1000000202), $session->diagnostics->conditions]);
    }

    public function testDropWarnsOfAnSridWithoutSystemUnderIfExists(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 1000000203')->statement;
        self::assertInstanceOf(DropSpatialReference::class, $statement);

        (new SpatialReferenceCommand())->drop($statement, $session);

        self::assertSame([['Warning', 3519, "There's no spatial reference system with SRID 1000000203."]], $session->diagnostics->conditions);
    }

    public function testReservedWarnsOfTheReservedRanges(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE SPATIAL REFERENCE SYSTEM 60000001 NAME 'a' DEFINITION '" . 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]' . "'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3715', 'The SRID range [60000000, 69999999] has been reserved for system use. SRSs in this range may be added, modified or removed without warning during upgrade.']], $warnings->rows);
    }

    public function testReservedWarnsWhenTheSystemOfWgs84IsDropped(): void
    {
        $session = (new Instance())->connect();
        (new SpatialReferenceCommand())->reserved(4326, $session);

        self::assertSame([['Warning', 3715, 'The SRID range [0, 32767] has been reserved for system use. SRSs in this range may be added, modified or removed without warning during upgrade.']], $session->diagnostics->conditions);
    }
}
