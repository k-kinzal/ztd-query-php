<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\SpatialDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialDefinition::class)]
#[Small]
final class SpatialDefinitionTest extends TestCase
{
    public function testValidAcceptsTheDefinitionOfWgs84(): void
    {
        self::assertTrue((new SpatialDefinition())->valid('GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4326"]]'));
    }

    public function testValidAcceptsLowerCaseKeywordsParenthesesAndSpaces(): void
    {
        self::assertTrue((new SpatialDefinition())->valid(' geogcs ( "x" , datum("d",spheroid("s",6378137,298.257223563)),primem("Greenwich",0),unit("degree",0.017453292519943278),axis("Lat",north),axis("Lon",east)) '));
    }

    public function testValidRefusesAGeographicSystemWithoutAxes(): void
    {
        self::assertFalse((new SpatialDefinition())->valid('GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278]]'));
    }

    public function testValidRefusesText(): void
    {
        self::assertSame([false, false, false], [(new SpatialDefinition())->valid('x'), (new SpatialDefinition())->valid('GEOGCS[]'), (new SpatialDefinition())->valid('GEOGCS["x"]')]);
    }

    public function testTokenizeSplitsWordsTextsNumbersAndBrackets(): void
    {
        self::assertSame([['word', 'AXIS'], ['open', '['], ['text', 'Lat'], ['comma', ','], ['number', '-1.5e3'], ['close', ']']], (new SpatialDefinition())->tokenize('AXIS["Lat", -1.5e3]'));
    }

    public function testTokenizeAnswersNullForAStrayCharacter(): void
    {
        self::assertNull((new SpatialDefinition())->tokenize('GEOGCS[;]'));
    }

    public function testGeographicReadsTheDatumAfterTheName(): void
    {
        self::assertFalse((new SpatialDefinition())->valid('GEOGCS["x",PRIMEM["G",0],DATUM["d",SPHEROID["s",1,2]],UNIT["u",1],AXIS["a",NORTH],AXIS["b",EAST]]'));
    }

    public function testProjectedReadsTheParametersOfTheProjection(): void
    {
        self::assertTrue((new SpatialDefinition())->valid('PROJCS["WGS 84 / Pseudo-Mercator",GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4326"]],PROJECTION["Popular Visualisation Pseudo Mercator",AUTHORITY["EPSG","1024"]],PARAMETER["Latitude of natural origin",0,AUTHORITY["EPSG","8801"]],PARAMETER["Longitude of natural origin",0,AUTHORITY["EPSG","8802"]],PARAMETER["False easting",0,AUTHORITY["EPSG","8806"]],PARAMETER["False northing",0,AUTHORITY["EPSG","8807"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["X",EAST],AXIS["Y",NORTH],AUTHORITY["EPSG","3857"]]'));
    }

    public function testDatumAcceptsTowgs84AfterTheSpheroid(): void
    {
        self::assertTrue((new SpatialDefinition())->valid('GEOGCS["ED50",DATUM["European Datum 1950",SPHEROID["International 1924",6378388,297,AUTHORITY["EPSG","7022"]],TOWGS84[-157.89,-17.16,-78.41,2.118,2.697,-1.434,-5.38],AUTHORITY["EPSG","6230"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4230"]]'));
    }

    public function testMeasuredNeedsANumber(): void
    {
        self::assertFalse((new SpatialDefinition())->valid('GEOGCS["x",DATUM["d",SPHEROID["s",1,2]],PRIMEM["G","0"],UNIT["u",1],AXIS["a",NORTH],AXIS["b",EAST]]'));
    }

    public function testAxisNeedsADirection(): void
    {
        self::assertFalse((new SpatialDefinition())->valid('GEOGCS["x",DATUM["d",SPHEROID["s",1,2]],PRIMEM["G",0],UNIT["u",1],AXIS["a","NORTH"],AXIS["b",EAST]]'));
    }

    public function testOpeningNeedsTheKeyword(): void
    {
        self::assertFalse((new SpatialDefinition())->valid('GEOCCS["x",DATUM["d",SPHEROID["s",1,2]],PRIMEM["G",0],UNIT["u",1],AXIS["a",NORTH],AXIS["b",EAST]]'));
    }

    public function testClosingAcceptsAnAuthority(): void
    {
        self::assertTrue((new SpatialDefinition())->valid('GEOGCS["x",DATUM["d",SPHEROID["s",1,2]],PRIMEM["G",0,AUTHORITY["E","1"]],UNIT["u",1],AXIS["a",NORTH],AXIS["b",EAST],AUTHORITY["E","2"]]'));
    }

    public function testKeywordIgnoresLetterCase(): void
    {
        $definition = new SpatialDefinition();
        $definition->valid('unit["u",1]');

        self::assertTrue($definition->keyword('UNIT'));
    }

    public function testPeekLooksAtTheCurrentToken(): void
    {
        self::assertFalse((new SpatialDefinition())->peek('word'));
    }

    public function testTakeReadsNothingAtTheEnd(): void
    {
        self::assertFalse((new SpatialDefinition())->take('close'));
    }
}
