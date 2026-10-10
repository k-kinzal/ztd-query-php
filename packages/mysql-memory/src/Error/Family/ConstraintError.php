<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error about the constraints of a table: generated columns, expression defaults, CHECK constraints and foreign keys.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Building the error of a violated foreign key
 *     \MySqlMemory\Error\Family\ConstraintError::NoReferencedRow->error('(`d`.`c`, CONSTRAINT `fk`)')->getMessage() // => 'Cannot add or update a child row: a foreign key constraint fails ((`d`.`c`, CONSTRAINT `fk`))'
 */
enum ConstraintError: int implements ErrorCode
{
    use CatalogedError;

    case CannotAddForeign = 1215;
    case ParentRowReferenced = 1217;
    case WrongForeignKey = 1239;
    case RowIsReferenced = 1451;
    case NoReferencedRow = 1452;
    case DropIndexForeignKey = 1553;
    case TruncateReferenced = 1701;
    case ForeignMissingIndex = 1822;
    case ForeignTableMissing = 1824;
    case DuplicateForeignName = 1826;
    case DropForeignColumn = 1828;
    case DropReferencedColumn = 1829;
    case ForeignNotNullSetNull = 1830;
    case ForeignCascadeDepth = 3008;
    case GeneratedSubquery = 3102;
    case GeneratedForeignAction = 3104;
    case GeneratedUnsupported = 3106;
    case GeneratedNotPrior = 3107;
    case GeneratedDependency = 3108;
    case GeneratedAutoIncrement = 3109;
    case WindowFunctionContext = 3593;
    case DropReferencedTable = 3730;
    case ForeignColumnMissing = 3734;
    case GeneratedFunction = 3763;
    case DefaultNotPrior = 3767;
    case DefaultAutoIncrement = 3768;
    case DefaultSubquery = 3769;
    case DefaultFunction = 3770;
    case DefaultVariable = 3772;
    case ForeignIncompatible = 3780;
    case CheckNotBoolean = 3812;
    case CheckOtherColumn = 3813;
    case CheckFunction = 3814;
    case CheckSubquery = 3815;
    case CheckVariable = 3816;
    case CheckAutoIncrement = 3818;
    case CheckColumnMissing = 3820;
    case DuplicateCheckName = 3822;
    case CheckReferentialColumn = 3823;
    case CheckColumnDependency = 3959;
    case ForeignMissingKey = 6125;
}
