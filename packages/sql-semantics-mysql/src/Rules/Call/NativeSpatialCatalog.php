<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

/**
 * The native spatial functions of MySQL, with the names 5.6 and 5.7 accept without the ST_ prefix.
 *
 * Part of MYSQL-NATIVE-FUNCTIONS-001. Every spatial name of the `func_array` of `sql/item_create.cc` in each release. Each row
 * of a name is a release mask (bit 0 for 5.6.51 to bit 8 for 9.1.0; bit 9
 * marks a function the server reserves for its own views), the minimum and
 * maximum number of arguments (-1 for any number) and the result code of
 * MYSQL-CALL-RESULT-001. A function whose result or argument count changed
 * between releases has one row per range of releases.
 * Source: https://github.com/mysql/mysql-server/blob/8.4/sql/item_create.cc,
 * https://dev.mysql.com/doc/refman/8.4/en/built-in-function-reference.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NativeSpatialCatalog
{
    /**
     * The rows by upper-case function name.
     */
    public const ROWS = [
        'AREA' => [[3, 1, 1, 'DY']], 'ASBINARY' => [[1, 1, 1, 'BY'], [2, 1, 2, 'BY']], 'ASTEXT' => [[1, 1, 1, 'TY'], [2, 1, 2, 'TY']],
        'ASWKB' => [[1, 1, 1, 'BY'], [2, 1, 2, 'BY']], 'ASWKT' => [[1, 1, 1, 'TY'], [2, 1, 2, 'TY']], 'BUFFER' => [[1, 2, 2, 'GY'], [2, 2, 5, 'GY']],
        'CENTROID' => [[3, 1, 1, 'GY']], 'CONVEXHULL' => [[2, 1, 1, 'GY']], 'CROSSES' => [[3, 2, 2, 'IY']], 'DIMENSION' => [[3, 1, 1, 'IY']],
        'DISJOINT' => [[3, 2, 2, 'IY']], 'DISTANCE' => [[2, 2, 3, 'DY']], 'ENDPOINT' => [[3, 1, 1, 'GY']], 'ENVELOPE' => [[3, 1, 1, 'GY']],
        'EQUALS' => [[3, 2, 2, 'IY']], 'EXTERIORRING' => [[3, 1, 1, 'GY']], 'GLENGTH' => [[3, 1, 1, 'DY']], 'INTERIORRINGN' => [[3, 2, 2, 'GY']],
        'INTERSECTS' => [[3, 2, 2, 'IY']], 'ISCLOSED' => [[3, 1, 1, 'IY']], 'ISEMPTY' => [[3, 1, 1, 'IY']], 'ISSIMPLE' => [[3, 1, 1, 'IY']],
        'MBRCONTAINS' => [[511, 2, 2, 'IY']], 'MBRCOVEREDBY' => [[510, 2, 2, 'IY']], 'MBRCOVERS' => [[510, 2, 2, 'IY']], 'MBRDISJOINT' => [[511, 2, 2, 'IY']],
        'MBREQUAL' => [[3, 2, 2, 'IY']], 'MBREQUALS' => [[510, 2, 2, 'IY']], 'MBRINTERSECTS' => [[511, 2, 2, 'IY']], 'MBROVERLAPS' => [[511, 2, 2, 'IY']],
        'MBRTOUCHES' => [[511, 2, 2, 'IY']], 'MBRWITHIN' => [[511, 2, 2, 'IY']], 'NUMGEOMETRIES' => [[3, 1, 1, 'IY']], 'NUMINTERIORRINGS' => [[3, 1, 1, 'IY']],
        'NUMPOINTS' => [[3, 1, 1, 'IY']], 'OVERLAPS' => [[3, 2, 2, 'IY']], 'SRID' => [[1, 1, 1, 'IY'], [2, 1, 2, 'IY']], 'STARTPOINT' => [[3, 1, 1, 'GY']],
        'ST_AREA' => [[511, 1, 1, 'DY']], 'ST_ASBINARY' => [[3, 1, 1, 'BY'], [508, 1, 2, 'BY']], 'ST_ASGEOJSON' => [[510, 1, 3, 'JY']],
        'ST_ASTEXT' => [[3, 1, 1, 'TY'], [508, 1, 2, 'TY']], 'ST_ASWKB' => [[3, 1, 1, 'BY'], [508, 1, 2, 'BY']],
        'ST_ASWKT' => [[3, 1, 1, 'TY'], [508, 1, 2, 'TY']], 'ST_BUFFER' => [[1, 2, 2, 'GY'], [510, 2, 5, 'GY']], 'ST_BUFFER_STRATEGY' => [[510, 1, 2, 'BY']],
        'ST_CENTROID' => [[511, 1, 1, 'GY']], 'ST_CONTAINS' => [[511, 2, 2, 'IY']], 'ST_CONVEXHULL' => [[510, 1, 1, 'GY']],
        'ST_CROSSES' => [[511, 2, 2, 'IY']], 'ST_DIFFERENCE' => [[511, 2, 2, 'GY']], 'ST_DIMENSION' => [[511, 1, 1, 'IY']],
        'ST_DISJOINT' => [[511, 2, 2, 'IY']], 'ST_DISTANCE' => [[3, 2, 2, 'DY'], [508, 2, 3, 'DY']], 'ST_DISTANCE_SPHERE' => [[510, 2, 3, 'DY']],
        'ST_ENDPOINT' => [[511, 1, 1, 'GY']], 'ST_ENVELOPE' => [[511, 1, 1, 'GY']], 'ST_EQUALS' => [[511, 2, 2, 'IY']],
        'ST_EXTERIORRING' => [[511, 1, 1, 'GY']], 'ST_FRECHETDISTANCE' => [[508, 2, 3, 'DY']], 'ST_GEOHASH' => [[510, 2, 3, 'TY']],
        'ST_GEOMCOLLFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_GEOMCOLLFROMTXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_GEOMCOLLFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_GEOMETRYCOLLECTIONFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_GEOMETRYCOLLECTIONFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_GEOMETRYFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_GEOMETRYFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_GEOMETRYN' => [[511, 2, 2, 'GY']], 'ST_GEOMETRYTYPE' => [[511, 1, 1, 'TY']],
        'ST_GEOMFROMGEOJSON' => [[510, 1, 3, 'GY']], 'ST_GEOMFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_GEOMFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_HAUSDORFFDISTANCE' => [[508, 2, 3, 'DY']], 'ST_INTERIORRINGN' => [[511, 2, 2, 'GY']],
        'ST_INTERSECTION' => [[511, 2, 2, 'GY']], 'ST_INTERSECTS' => [[511, 2, 2, 'IY']], 'ST_ISCLOSED' => [[511, 1, 1, 'IY']],
        'ST_ISEMPTY' => [[511, 1, 1, 'IY']], 'ST_ISSIMPLE' => [[511, 1, 1, 'IY']], 'ST_ISVALID' => [[510, 1, 1, 'IY']],
        'ST_LATFROMGEOHASH' => [[510, 1, 1, 'DY']], 'ST_LATITUDE' => [[508, 1, 1, 'DY'], [508, 2, 2, 'GY']],
        'ST_LENGTH' => [[3, 1, 1, 'DY'], [508, 1, 2, 'DY']], 'ST_LINEFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_LINEFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_LINEINTERPOLATEPOINT' => [[508, 2, 2, 'GY']],
        'ST_LINEINTERPOLATEPOINTS' => [[508, 2, 2, 'GY']], 'ST_LINESTRINGFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_LINESTRINGFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_LONGFROMGEOHASH' => [[510, 1, 1, 'DY']],
        'ST_LONGITUDE' => [[508, 1, 1, 'DY'], [508, 2, 2, 'GY']], 'ST_MAKEENVELOPE' => [[510, 2, 2, 'GY']],
        'ST_MLINEFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MLINEFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_MPOINTFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MPOINTFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_MPOLYFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MPOLYFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_MULTILINESTRINGFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MULTILINESTRINGFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_MULTIPOINTFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MULTIPOINTFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_MULTIPOLYGONFROMTEXT' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_MULTIPOLYGONFROMWKB' => [[2, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_NUMGEOMETRIES' => [[511, 1, 1, 'IY']], 'ST_NUMINTERIORRING' => [[510, 1, 1, 'IY']], 'ST_NUMINTERIORRINGS' => [[511, 1, 1, 'IY']],
        'ST_NUMPOINTS' => [[511, 1, 1, 'IY']], 'ST_OVERLAPS' => [[511, 2, 2, 'IY']], 'ST_POINTATDISTANCE' => [[508, 2, 2, 'GY']],
        'ST_POINTFROMGEOHASH' => [[510, 2, 2, 'GY']], 'ST_POINTFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_POINTFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_POINTN' => [[511, 2, 2, 'GY']],
        'ST_POLYFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_POLYFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_POLYGONFROMTEXT' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']], 'ST_POLYGONFROMWKB' => [[3, 1, 2, 'GY'], [508, 1, 3, 'GY']],
        'ST_SIMPLIFY' => [[510, 2, 2, 'GY']], 'ST_SRID' => [[3, 1, 1, 'UY'], [508, 1, 1, 'UY'], [508, 2, 2, 'GY']], 'ST_STARTPOINT' => [[511, 1, 1, 'GY']],
        'ST_SWAPXY' => [[508, 1, 1, 'GY']], 'ST_SYMDIFFERENCE' => [[511, 2, 2, 'GY']], 'ST_TOUCHES' => [[511, 2, 2, 'IY']],
        'ST_TRANSFORM' => [[508, 2, 2, 'GY']], 'ST_UNION' => [[511, 2, 2, 'GY']], 'ST_VALIDATE' => [[510, 1, 1, 'GY']], 'ST_WITHIN' => [[511, 2, 2, 'IY']],
        'ST_X' => [[3, 1, 1, 'DY'], [508, 1, 1, 'DY'], [508, 2, 2, 'GY']], 'ST_Y' => [[3, 1, 1, 'DY'], [508, 1, 1, 'DY'], [508, 2, 2, 'GY']],
        'TOUCHES' => [[3, 2, 2, 'IY']], 'WITHIN' => [[3, 2, 2, 'IY']], 'X' => [[1, 1, 1, 'DY'], [2, 1, 2, 'DY']], 'Y' => [[1, 1, 1, 'DY'], [2, 1, 2, 'DY']],
    ];
}
