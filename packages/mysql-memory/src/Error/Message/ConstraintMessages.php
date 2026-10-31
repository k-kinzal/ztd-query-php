<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the ConstraintError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class ConstraintMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'CannotAddForeign' => ['HY000', 'Cannot add foreign key constraint'],
        'ParentRowReferenced' => ['23000', 'Cannot delete or update a parent row: a foreign key constraint fails'],
        'WrongForeignKey' => ['42000', 'Incorrect foreign key definition for \'%s\': %s'],
        'RowIsReferenced' => ['23000', 'Cannot delete or update a parent row: a foreign key constraint fails (%s)'],
        'NoReferencedRow' => ['23000', 'Cannot add or update a child row: a foreign key constraint fails (%s)'],
        'DropIndexForeignKey' => ['HY000', 'Cannot drop index \'%s\': needed in a foreign key constraint'],
        'TruncateReferenced' => ['42000', 'Cannot truncate a table referenced in a foreign key constraint (%s)'],
        'ForeignMissingIndex' => ['HY000', 'Failed to add the foreign key constraint. Missing index for constraint \'%s\' in the referenced table \'%s\''],
        'ForeignTableMissing' => ['HY000', 'Failed to open the referenced table \'%s\''],
        'DuplicateForeignName' => ['HY000', 'Duplicate foreign key constraint name \'%s\''],
        'DropForeignColumn' => ['HY000', 'Cannot drop column \'%s\': needed in a foreign key constraint \'%s\''],
        'DropReferencedColumn' => ['HY000', 'Cannot drop column \'%s\': needed in a foreign key constraint \'%s\' of table \'%s\''],
        'ForeignNotNullSetNull' => ['HY000', 'Column \'%s\' cannot be NOT NULL: needed in a foreign key constraint \'%s\' SET NULL'],
        'ForeignCascadeDepth' => ['HY000', 'Foreign key cascade delete/update exceeds max depth of %d.'],
        'GeneratedSubquery' => ['HY000', 'Expression of generated column \'%s\' contains a disallowed function.'],
        'GeneratedForeignAction' => ['HY000', 'Cannot define foreign key with %s clause on a generated column.'],
        'GeneratedUnsupported' => ['HY000', '\'%s\' is not supported for generated columns.'],
        'GeneratedNotPrior' => ['HY000', 'Generated column can refer only to generated columns defined prior to it.'],
        'GeneratedDependency' => ['HY000', 'Column \'%s\' has a generated column dependency.'],
        'GeneratedAutoIncrement' => ['HY000', 'Generated column \'%s\' cannot refer to auto-increment column.'],
        'WindowFunctionContext' => ['HY000', 'You cannot use the window function \'%s\' in this context.\''],
        'DropReferencedTable' => ['HY000', 'Cannot drop table \'%s\' referenced by a foreign key constraint \'%s\' on table \'%s\'.'],
        'ForeignColumnMissing' => ['HY000', 'Failed to add the foreign key constraint. Missing column \'%s\' for constraint \'%s\' in the referenced table \'%s\''],
        'FunctionalTruncated' => ['01000', 'Data truncated for functional index \'%s\' at row %d'],
        'FunctionalRange' => ['22003', 'Value is out of range for functional index \'%s\' at row %d'],
        'FunctionalJsonCast' => ['22018', 'Invalid JSON value for CAST for functional index \'%s\'.'],
        'FunctionalTooLong' => ['22001', 'Data too long for functional index \'%s\'.'],
        'FunctionalNested' => ['HY000', 'Cannot store an array or an object in a scalar key part of the index \'%s\'.'],
        'FunctionalJsonGeometry' => ['42000', 'Cannot create a functional index on a function that returns a JSON or GEOMETRY value.'],
        'FunctionalAutoIncrement' => ['HY000', 'Functional index \'%s\' cannot refer to an auto-increment column.'],
        'FunctionalPrimary' => ['HY000', 'The primary key cannot be a functional index'],
        'FunctionalIndexFunction' => ['HY000', 'Expression of functional index \'%s\' contains a disallowed function.'],
        'FunctionalRow' => ['HY000', 'Expression of functional index \'%s\' cannot refer to a row value.'],
        'FunctionalFullText' => ['HY000', 'Fulltext functional index is not supported.'],
        'FunctionalSpatial' => ['HY000', 'Spatial functional index is not supported.'],
        'FunctionalColumn' => ['HY000', 'Functional index on a column is not supported. Consider using a regular index instead.'],
        'GeneratedFunction' => ['HY000', 'Expression of generated column \'%s\' contains a disallowed function: %s.'],
        'GeneratedRow' => ['HY000', 'Expression of generated column \'%s\' cannot refer to a row value.'],
        'DefaultNotPrior' => ['HY000', 'Default value expression of column \'%s\' cannot refer to a column defined after it if that column is a generated column or has an expression as default value.'],
        'DefaultAutoIncrement' => ['HY000', 'Default value expression of column \'%s\' cannot refer to an auto-increment column.'],
        'DefaultSubquery' => ['HY000', 'Default value expression of column \'%s\' contains a disallowed function.'],
        'DefaultFunction' => ['HY000', 'Default value expression of column \'%s\' contains a disallowed function: %s.'],
        'DefaultRow' => ['HY000', 'Default value expression of column \'%s\' cannot refer to a row value.'],
        'DefaultVariable' => ['HY000', 'Default value expression of column \'%s\' cannot refer user or system variables.'],
        'ForeignIncompatible' => ['HY000', 'Referencing column \'%s\' and referenced column \'%s\' in foreign key constraint \'%s\' are incompatible.'],
        'CheckNotBoolean' => ['HY000', 'An expression of non-boolean type specified to a check constraint \'%s\'.'],
        'CheckOtherColumn' => ['HY000', 'Column check constraint \'%s\' references other column.'],
        'CheckFunction' => ['HY000', 'An expression of a check constraint \'%s\' contains disallowed function: %s.'],
        'CheckSubquery' => ['HY000', 'An expression of a check constraint \'%s\' contains disallowed function.'],
        'CheckVariable' => ['HY000', 'An expression of a check constraint \'%s\' cannot refer to a user or system variable.'],
        'CheckAutoIncrement' => ['HY000', 'Check constraint \'%s\' cannot refer to an auto-increment column.'],
        'CheckColumnMissing' => ['HY000', 'Check constraint \'%s\' refers to non-existing column \'%s\'.'],
        'DuplicateCheckName' => ['HY000', 'Duplicate check constraint name \'%s\'.'],
        'CheckReferentialColumn' => ['HY000', 'Column \'%s\' cannot be used in a check constraint \'%s\': needed in a foreign key constraint \'%s\' referential action.'],
        'FunctionalDependency' => ['HY000', 'Column \'%s\' has a functional index dependency and cannot be dropped or renamed.'],
        'CheckColumnDependency' => ['HY000', 'Check constraint \'%s\' uses column \'%s\', hence column cannot be dropped or renamed.'],
        'ForeignMissingKey' => ['HY000', 'Failed to add the foreign key constraint. Missing unique key for constraint \'%s\' in the referenced table \'%s\''],
    ];
}
