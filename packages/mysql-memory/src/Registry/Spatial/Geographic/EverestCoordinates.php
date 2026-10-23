<?php

declare(strict_types=1);

namespace MySqlMemory\Registry\Spatial\Geographic;

/**
 * Built-in spatial reference definitions: Geographic; Everest.
 * Each entry owns its name, authority, authority identifier, WKT definition and description.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-reference-systems.html.
 *
 * @visibility MySqlMemory
 */
final class EverestCoordinates
{
    /**
     * @var array<int, array{string, ?string, ?int, string, ?string}>
     */
    private const SYSTEMS = [
        4015 => ['Unknown datum based upon the Everest 1830 (1937 Adjustment) ellipsoid', 'EPSG', 4015, 'GEOGCS["Unknown datum based upon the Everest 1830 (1937 Adjustment) ellipsoid",DATUM["Not specified (based on Everest 1830 (1937 Adjustment) ellipsoid)",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],AUTHORITY["EPSG","6015"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4015"]]', null],
        4016 => ['Unknown datum based upon the Everest 1830 (1967 Definition) ellipsoid', 'EPSG', 4016, 'GEOGCS["Unknown datum based upon the Everest 1830 (1967 Definition) ellipsoid",DATUM["Not specified (based on Everest 1830 (1967 Definition) ellipsoid)",SPHEROID["Everest 1830 (1967 Definition)",6377298.556,300.8017,AUTHORITY["EPSG","7016"]],AUTHORITY["EPSG","6016"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4016"]]', null],
        4018 => ['Unknown datum based upon the Everest 1830 Modified ellipsoid', 'EPSG', 4018, 'GEOGCS["Unknown datum based upon the Everest 1830 Modified ellipsoid",DATUM["Not specified (based on Everest 1830 Modified ellipsoid)",SPHEROID["Everest 1830 Modified",6377304.063,300.8017,AUTHORITY["EPSG","7018"]],AUTHORITY["EPSG","6018"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4018"]]', null],
        4042 => ['Unknown datum based upon the Everest (1830 Definition) ellipsoid', 'EPSG', 4042, 'GEOGCS["Unknown datum based upon the Everest (1830 Definition) ellipsoid",DATUM["Not specified (based on Everest (1830 Definition) ellipsoid)",SPHEROID["Everest (1830 Definition)",6377299.36559538,300.80172554336184,AUTHORITY["EPSG","7042"]],AUTHORITY["EPSG","6042"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4042"]]', null],
        4044 => ['Unknown datum based upon the Everest 1830 (1962 Definition) ellipsoid', 'EPSG', 4044, 'GEOGCS["Unknown datum based upon the Everest 1830 (1962 Definition) ellipsoid",DATUM["Not specified (based on Everest 1830 (1962 Definition) ellipsoid)",SPHEROID["Everest 1830 (1962 Definition)",6377301.243,300.8017255,AUTHORITY["EPSG","7044"]],AUTHORITY["EPSG","6044"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4044"]]', null],
        4045 => ['Unknown datum based upon the Everest 1830 (1975 Definition) ellipsoid', 'EPSG', 4045, 'GEOGCS["Unknown datum based upon the Everest 1830 (1975 Definition) ellipsoid",DATUM["Not specified (based on Everest 1830 (1975 Definition) ellipsoid)",SPHEROID["Everest 1830 (1975 Definition)",6377299.151,300.8017255,AUTHORITY["EPSG","7045"]],AUTHORITY["EPSG","6045"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4045"]]', null],
        4131 => ['Indian 1960', 'EPSG', 4131, 'GEOGCS["Indian 1960",DATUM["Indian 1960",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[182,915,344,0,0,0,0],AUTHORITY["EPSG","6131"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4131"]]', null],
        4144 => ['Kalianpur 1937', 'EPSG', 4144, 'GEOGCS["Kalianpur 1937",DATUM["Kalianpur 1937",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[214,804,268,0,0,0,0],AUTHORITY["EPSG","6144"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4144"]]', null],
        4145 => ['Kalianpur 1962', 'EPSG', 4145, 'GEOGCS["Kalianpur 1962",DATUM["Kalianpur 1962",SPHEROID["Everest 1830 (1962 Definition)",6377301.243,300.8017255,AUTHORITY["EPSG","7044"]],TOWGS84[230.25,632.76,161.03,-1.114,1.115,1.212,12.584],AUTHORITY["EPSG","6145"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4145"]]', null],
        4146 => ['Kalianpur 1975', 'EPSG', 4146, 'GEOGCS["Kalianpur 1975",DATUM["Kalianpur 1975",SPHEROID["Everest 1830 (1975 Definition)",6377299.151,300.8017255,AUTHORITY["EPSG","7045"]],TOWGS84[295,736,257,0,0,0,0],AUTHORITY["EPSG","6146"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4146"]]', null],
        4239 => ['Indian 1954', 'EPSG', 4239, 'GEOGCS["Indian 1954",DATUM["Indian 1954",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[217,823,299,0,0,0,0],AUTHORITY["EPSG","6239"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4239"]]', null],
        4240 => ['Indian 1975', 'EPSG', 4240, 'GEOGCS["Indian 1975",DATUM["Indian 1975",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[210,814,289,0,0,0,0],AUTHORITY["EPSG","6240"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4240"]]', null],
        4243 => ['Kalianpur 1880', 'EPSG', 4243, 'GEOGCS["Kalianpur 1880",DATUM["Kalianpur 1880",SPHEROID["Everest (1830 Definition)",6377299.36559538,300.80172554336184,AUTHORITY["EPSG","7042"]],AUTHORITY["EPSG","6243"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4243"]]', null],
        4244 => ['Kandawala', 'EPSG', 4244, 'GEOGCS["Kandawala",DATUM["Kandawala",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[-97,787,86,0,0,0,0],AUTHORITY["EPSG","6244"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4244"]]', null],
        4245 => ['Kertau 1968', 'EPSG', 4245, 'GEOGCS["Kertau 1968",DATUM["Kertau 1968",SPHEROID["Everest 1830 Modified",6377304.063,300.8017,AUTHORITY["EPSG","7018"]],TOWGS84[-11,851,5,0,0,0,0],AUTHORITY["EPSG","6245"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4245"]]', null],
        4298 => ['Timbalai 1948', 'EPSG', 4298, 'GEOGCS["Timbalai 1948",DATUM["Timbalai 1948",SPHEROID["Everest 1830 (1967 Definition)",6377298.556,300.8017,AUTHORITY["EPSG","7016"]],TOWGS84[-679,669,-48,0,0,0,0],AUTHORITY["EPSG","6298"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4298"]]', null],
        4682 => ['Gulshan 303', 'EPSG', 4682, 'GEOGCS["Gulshan 303",DATUM["Gulshan 303",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[283.7,735.9,261.1,0,0,0,0],AUTHORITY["EPSG","6682"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4682"]]', null],
        4751 => ['Kertau (RSO)', 'EPSG', 4751, 'GEOGCS["Kertau (RSO)",DATUM["Kertau (RSO)",SPHEROID["Everest 1830 (RSO 1969)",6377295.664,300.8017,AUTHORITY["EPSG","7056"]],AUTHORITY["EPSG","6751"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4751"]]', null],
        5233 => ['SLD99', 'EPSG', 5233, 'GEOGCS["SLD99",DATUM["Sri Lanka Datum 1999",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[-0.293,766.95,87.713,0.195704,1.695068,3.473016,-0.039338],AUTHORITY["EPSG","1053"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","5233"]]', null],
        6207 => ['Nepal 1981', 'EPSG', 6207, 'GEOGCS["Nepal 1981",DATUM["Nepal 1981",SPHEROID["Everest 1830 (1937 Adjustment)",6377276.345,300.8017,AUTHORITY["EPSG","7015"]],TOWGS84[293.17,726.18,245.36,0,0,0,0],AUTHORITY["EPSG","1111"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","6207"]]', null],
    ];
    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}>
     */
    public static function entries(): array
    {
        return self::SYSTEMS;
    }

}
