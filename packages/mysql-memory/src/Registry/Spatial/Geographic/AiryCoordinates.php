<?php

declare(strict_types=1);

namespace MySqlMemory\Registry\Spatial\Geographic;

/**
 * Built-in spatial reference definitions: Geographic; Airy.
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
        4001 => ['Unknown datum based upon the Airy 1830 ellipsoid', 'EPSG', 4001, 'GEOGCS["Unknown datum based upon the Airy 1830 ellipsoid",DATUM["Not specified (based on Airy 1830 ellipsoid)",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],AUTHORITY["EPSG","6001"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4001"]]', null],
        4002 => ['Unknown datum based upon the Airy Modified 1849 ellipsoid', 'EPSG', 4002, 'GEOGCS["Unknown datum based upon the Airy Modified 1849 ellipsoid",DATUM["Not specified (based on Airy Modified 1849 ellipsoid)",SPHEROID["Airy Modified 1849",6377340.189,299.3249646,AUTHORITY["EPSG","7002"]],AUTHORITY["EPSG","6002"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4002"]]', null],
        4188 => ['OSNI 1952', 'EPSG', 4188, 'GEOGCS["OSNI 1952",DATUM["OSNI 1952",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6188"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4188"]]', null],
        4277 => ['OSGB 1936', 'EPSG', 4277, 'GEOGCS["OSGB 1936",DATUM["OSGB 1936",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],TOWGS84[446.448,-125.157,542.06,0.15,0.247,0.842,-20.489],AUTHORITY["EPSG","6277"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4277"]]', null],
        4278 => ['OSGB70', 'EPSG', 4278, 'GEOGCS["OSGB70",DATUM["OSGB 1970 (SN)",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],AUTHORITY["EPSG","6278"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4278"]]', null],
        4279 => ['OS(SN)80', 'EPSG', 4279, 'GEOGCS["OS(SN)80",DATUM["OS (SN) 1980",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],AUTHORITY["EPSG","6279"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4279"]]', null],
        4299 => ['TM65', 'EPSG', 4299, 'GEOGCS["TM65",DATUM["TM65",SPHEROID["Airy Modified 1849",6377340.189,299.3249646,AUTHORITY["EPSG","7002"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6299"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4299"]]', null],
        4300 => ['TM75', 'EPSG', 4300, 'GEOGCS["TM75",DATUM["Geodetic Datum of 1965",SPHEROID["Airy Modified 1849",6377340.189,299.3249646,AUTHORITY["EPSG","7002"]],TOWGS84[482.5,-130.6,564.6,-1.042,-0.214,-0.631,8.15],AUTHORITY["EPSG","6300"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4300"]]', null],
    ];
    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}>
     */
    public static function entries(): array
    {
        return self::SYSTEMS;
    }

}
