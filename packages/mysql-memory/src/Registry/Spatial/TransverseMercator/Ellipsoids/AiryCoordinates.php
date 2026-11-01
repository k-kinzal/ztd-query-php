<?php

declare(strict_types=1);

namespace MySqlMemory\Registry\Spatial\TransverseMercator\Ellipsoids;

/**
 * Built-in spatial reference definitions: TransverseMercator; Ellipsoids; Airy.
 * Each entry owns its name, authority, authority identifier, WKT definition and description.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html.
 *
 * @visibility MySqlMemory
 */
final class AiryCoordinates
{
    /**
     * @var array<int, array{string, ?string, ?int, string, ?string}>
     */
    private const SYSTEMS = [
        27700 => ['OSGB 1936 / British National Grid', 'EPSG', 27700, 'PROJCS["OSGB 1936 / British National Grid",GEOGCS["OSGB 1936",DATUM["OSGB 1936",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],TOWGS84[446.448,-125.157,542.06,0.15,0.247,0.842,-20.489],AUTHORITY["EPSG","6277"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4277"]],PROJECTION["Transverse Mercator",AUTHORITY["EPSG","9807"]],PARAMETER["Latitude of natural origin",49,AUTHORITY["EPSG","8801"]],PARAMETER["Longitude of natural origin",-2,AUTHORITY["EPSG","8802"]],PARAMETER["Scale factor at natural origin",0.9996012717,AUTHORITY["EPSG","8805"]],PARAMETER["False easting",400000,AUTHORITY["EPSG","8806"]],PARAMETER["False northing",-100000,AUTHORITY["EPSG","8807"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["E",EAST],AXIS["N",NORTH],AUTHORITY["EPSG","27700"]]', null],
        29901 => ['OSNI 1952 / Irish National Grid', 'EPSG', 29901, 'PROJCS["OSNI 1952 / Irish National Grid",GEOGCS["OSNI 1952",DATUM["OSNI 1952",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6188"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4188"]],PROJECTION["Transverse Mercator",AUTHORITY["EPSG","9807"]],PARAMETER["Latitude of natural origin",53.5111111111111,AUTHORITY["EPSG","8801"]],PARAMETER["Longitude of natural origin",-8,AUTHORITY["EPSG","8802"]],PARAMETER["Scale factor at natural origin",1,AUTHORITY["EPSG","8805"]],PARAMETER["False easting",200000,AUTHORITY["EPSG","8806"]],PARAMETER["False northing",250000,AUTHORITY["EPSG","8807"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["E",EAST],AXIS["N",NORTH],AUTHORITY["EPSG","29901"]]', null],
        29902 => ['TM65 / Irish Grid', 'EPSG', 29902, 'PROJCS["TM65 / Irish Grid",GEOGCS["TM65",DATUM["TM65",SPHEROID["Airy Modified 1849",6377340.189,299.3249646,AUTHORITY["EPSG","7002"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6299"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4299"]],PROJECTION["Transverse Mercator",AUTHORITY["EPSG","9807"]],PARAMETER["Latitude of natural origin",53.5111111111111,AUTHORITY["EPSG","8801"]],PARAMETER["Longitude of natural origin",-8,AUTHORITY["EPSG","8802"]],PARAMETER["Scale factor at natural origin",1.000035,AUTHORITY["EPSG","8805"]],PARAMETER["False easting",200000,AUTHORITY["EPSG","8806"]],PARAMETER["False northing",250000,AUTHORITY["EPSG","8807"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["E",EAST],AXIS["N",NORTH],AUTHORITY["EPSG","29902"]]', null],
        29903 => ['TM75 / Irish Grid', 'EPSG', 29903, 'PROJCS["TM75 / Irish Grid",GEOGCS["TM75",DATUM["Geodetic Datum of 1965",SPHEROID["Airy Modified 1849",6377340.189,299.3249646,AUTHORITY["EPSG","7002"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6300"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4300"]],PROJECTION["Transverse Mercator",AUTHORITY["EPSG","9807"]],PARAMETER["Latitude of natural origin",53.5111111111111,AUTHORITY["EPSG","8801"]],PARAMETER["Longitude of natural origin",-8,AUTHORITY["EPSG","8802"]],PARAMETER["Scale factor at natural origin",1.000035,AUTHORITY["EPSG","8805"]],PARAMETER["False easting",200000,AUTHORITY["EPSG","8806"]],PARAMETER["False northing",250000,AUTHORITY["EPSG","8807"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["E",EAST],AXIS["N",NORTH],AUTHORITY["EPSG","29903"]]', null],
    ];
    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}>
     */
    public static function entries(): array
    {
        return self::SYSTEMS;
    }

}
