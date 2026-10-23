<?php

declare(strict_types=1);

namespace MySqlMemory\Registry\Spatial\LambertConformal;

/**
 * Built-in spatial reference definitions: LambertConformal; Wgs72Broadcast.
 * Each entry owns its name, authority, authority identifier, WKT definition and description.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html.
 *
 * @visibility MySqlMemory
 */
final class Wgs72BroadcastCoordinates
{
    /**
     * @var array<int, array{string, ?string, ?int, string, ?string}>
     */
    private const SYSTEMS = [
        3415 => ['WGS 72BE / South China Sea Lambert', 'EPSG', 3415, 'PROJCS["WGS 72BE / South China Sea Lambert",GEOGCS["WGS 72BE",DATUM["WGS 72 Transit Broadcast Ephemeris",SPHEROID["WGS 72",6378135,298.26,AUTHORITY["EPSG","7043"]],TOWGS84[0,0,1.9,0,0,0.814,-0.38],AUTHORITY["EPSG","6324"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4324"]],PROJECTION["Lambert Conic Conformal (2SP)",AUTHORITY["EPSG","9802"]],PARAMETER["Latitude of false origin",21,AUTHORITY["EPSG","8821"]],PARAMETER["Longitude of false origin",114,AUTHORITY["EPSG","8822"]],PARAMETER["Latitude of 1st standard parallel",18,AUTHORITY["EPSG","8823"]],PARAMETER["Latitude of 2nd standard parallel",24,AUTHORITY["EPSG","8824"]],PARAMETER["Easting at false origin",500000,AUTHORITY["EPSG","8826"]],PARAMETER["Northing at false origin",500000,AUTHORITY["EPSG","8827"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],AXIS["E",EAST],AXIS["N",NORTH],AUTHORITY["EPSG","3415"]]', null],
    ];
    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}>
     */
    public static function entries(): array
    {
        return self::SYSTEMS;
    }

}
