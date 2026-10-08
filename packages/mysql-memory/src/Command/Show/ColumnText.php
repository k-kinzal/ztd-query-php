<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Writes a column of a table as SHOW CREATE TABLE and SHOW COLUMNS write it: its type, its default and its extra attributes.
 *
 * A type is written in lower case with its length, precision or members. The display width of
 * an integer type is written only for TINYINT(1), the type of a boolean, and for a ZEROFILL
 * column, which is UNSIGNED. A spatial type is written as declared. A column default is
 * written as the text of the stored value; a BIT default as a bit literal; a binary string in
 * SHOW COLUMNS as a hexadecimal literal of its bytes without trailing zero bytes. A default the
 * server computes, the current time or an expression, is DEFAULT_GENERATED (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 *
 * @visibility MySqlMemory
 */
final class ColumnText
{
    /**
     * Answers the type a column of a table was declared with, or null when the declaration is not known.
     */
    public function written(TableDefinition $table, ColumnDefinition $column): ?TypeName
    {
        return $this->element($table, $column)?->specification->dataType();
    }

    /**
     * Answers the definition a column of a table was declared by, or null when the declaration is not known.
     */
    public function element(TableDefinition $table, ColumnDefinition $column): ?ColumnElement
    {
        foreach ($table->statement->elements ?? [] as $element) {
            if ($element instanceof ColumnElement && strcasecmp($element->name->column->value, $column->name) === 0) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Tells whether a column definition names its character set or collation, rather than taking the table's.
     */
    public function explicit(?ColumnElement $element): bool
    {
        if ($element === null) {
            return false;
        }
        $specification = $element->specification;
        $type = $specification->dataType();
        if (($type instanceof Character && ($type->charset !== null || $type->national)) || ($type instanceof Enumeration && $type->charset !== null)) {
            return true;
        }
        if ($specification instanceof GeneratedColumn && $specification->collation !== null) {
            return true;
        }
        foreach ($specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->attributes : [] as $attribute) {
            if ($attribute instanceof CollateAttribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes the type of a column, from its domain and the type it was declared with.
     */
    public function type(Domain $domain, ?TypeName $written = null): string
    {
        if ($written instanceof Spatial) {
            return $written->kind === SpatialKind::GeometryCollection ? 'geomcollection' : strtolower($written->kind->value);
        }
        $zeroFill = ($written instanceof Integral || $written instanceof Decimal || $written instanceof Floating) && in_array(NumericModifier::Zerofill, $written->modifiers, true);
        $unsigned = ($domain->unsigned || $zeroFill) && $domain->kind !== Kind::Bit && $domain->kind !== Kind::Year ? ' unsigned' . ($zeroFill ? ' zerofill' : '') : '';
        $fraction = $domain->decimals > 0 ? '(' . $domain->decimals . ')' : '';
        $binary = $domain->collation->bytes();
        $boolean = $written instanceof Elementary && $written->kind === ElementaryKind::Boolean;
        $width = match (true) {
            $zeroFill => '(' . ($domain->display ?? $domain->length) . ')',
            $boolean || ($domain->field === Field::Tiny && $domain->display === 1 && !$domain->unsigned) => '(1)',
            default => '',
        };

        return match ($domain->field) {
            Field::Tiny => 'tinyint' . $width . $unsigned,
            Field::Short => 'smallint' . $width . $unsigned,
            Field::Int24 => 'mediumint' . $width . $unsigned,
            Field::Long => 'int' . $width . $unsigned,
            Field::LongLong => 'bigint' . $width . $unsigned,
            Field::Float, Field::Double => ($domain->field === Field::Float ? 'float' : 'double') . ($domain->decimals < Domain::NOT_FIXED ? '(' . $domain->length . ',' . $domain->decimals . ')' : '') . $unsigned,
            Field::Decimal, Field::NewDecimal => 'decimal(' . $domain->precision() . ',' . $domain->decimals . ')' . $unsigned,
            Field::Bit => 'bit(' . $domain->length . ')',
            Field::Date, Field::NewDate => 'date',
            Field::Time => 'time' . $fraction,
            Field::DateTime => 'datetime' . $fraction,
            Field::Timestamp => 'timestamp' . $fraction,
            Field::Year => 'year',
            Field::String => ($binary ? 'binary(' : 'char(') . $domain->length . ')',
            Field::VarChar, Field::VarString => ($binary ? 'varbinary(' : 'varchar(') . $domain->length . ')',
            Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob => $this->blob($domain),
            Field::Enum, Field::Set => ($domain->field === Field::Enum ? 'enum(' : 'set(') . implode(',', array_map(fn (string $member): string => $this->quoted($this->utf8($member, $domain)), $domain->members)) . ')',
            Field::Json => 'json',
            Field::Geometry => 'geometry',
            Field::Vector => 'vector(' . $domain->length . ')',
            Field::Null => 'null',
        };
    }

    /**
     * Writes the type of a BLOB or TEXT column from the length it holds.
     */
    public function blob(Domain $domain): string
    {
        $size = match (true) {
            $domain->length <= 255 => 'tiny',
            $domain->length <= 65535 => '',
            $domain->length <= 16777215 => 'medium',
            default => 'long',
        };

        return $size . ($domain->collation->bytes() ? 'blob' : 'text');
    }

    /**
     * Tells whether a column holds text in a character set: a character string, an ENUM or a SET.
     */
    public function textual(Domain $domain): bool
    {
        return $domain->kind === Kind::String && !$domain->collation->bytes() && $domain->field !== Field::Geometry;
    }

    /**
     * Writes the default of a column as SHOW COLUMNS reports it, or null when it has none or it is NULL.
     */
    public function shownDefault(ColumnDefinition $column): ?string
    {
        $default = $column->default;
        if (!$default->declared || $column->autoIncrement) {
            return null;
        }
        if ($default->now || $default->expression !== null) {
            return $default->text;
        }
        if ($default->value === null) {
            return null;
        }
        $domain = $column->domain;
        if ($domain->kind === Kind::Bit) {
            return $this->bits($default->value);
        }
        if ($domain->kind === Kind::String && $domain->collation->bytes() && $domain->field !== Field::Blob) {
            $bytes = rtrim((string) $default->value, "\0");

            return $bytes === '' ? '' : '0x' . bin2hex($bytes);
        }

        return $this->utf8((string) Convert::toText($default->value, $domain), $domain);
    }

    /**
     * Writes the default clause of a column as SHOW CREATE TABLE writes it, or the empty string for none.
     *
     * A BLOB or TEXT column has no DEFAULT NULL written; a JSON or spatial one has.
     */
    public function createDefault(ColumnDefinition $column, ?TypeName $written = null): string
    {
        $default = $column->default;
        $domain = $column->domain;
        if ($column->autoIncrement || !$default->declared) {
            return '';
        }
        if ($default->now) {
            return ' DEFAULT ' . $default->text;
        }
        if ($default->expression !== null) {
            return ' DEFAULT (' . $default->text . ')';
        }
        if ($default->value === null) {
            return $domain->field->blob() && $domain->field !== Field::Json && !$written instanceof Spatial ? '' : ' DEFAULT NULL';
        }
        if ($domain->kind === Kind::Bit) {
            return ' DEFAULT ' . $this->bits($default->value);
        }
        if ($domain->kind === Kind::String && $domain->collation->bytes()) {
            return ' DEFAULT ' . $this->quoted((string) $default->value);
        }

        return ' DEFAULT ' . $this->quoted($this->utf8((string) Convert::toText($default->value, $domain), $domain));
    }

    /**
     * Writes the extra attributes of a column as SHOW COLUMNS reports them.
     */
    public function extra(ColumnDefinition $column): string
    {
        $extra = [];
        if ($column->autoIncrement) {
            $extra[] = 'auto_increment';
        }
        if ($column->default->declared && ($column->default->now || $column->default->expression !== null) && !$column->autoIncrement) {
            $extra[] = 'DEFAULT_GENERATED';
        }
        if ($column->onUpdateNow) {
            $extra[] = 'on update CURRENT_TIMESTAMP' . ($column->domain->decimals > 0 ? '(' . $column->domain->decimals . ')' : '');
        }
        if ($column->invisible) {
            $extra[] = 'INVISIBLE';
        }

        return implode(' ', $extra);
    }

    /**
     * Writes a bit value as a bit literal: b'' followed by its bits without leading zeros.
     */
    public function bits(int|float|string $value): string
    {
        $number = 0;
        if (is_string($value)) {
            foreach (str_split($value) as $byte) {
                $number = ($number << 8) | ord($byte);
            }
        } else {
            $number = (int) $value;
        }

        return "b'" . decbin($number) . "'";
    }

    /**
     * Quotes a string as SHOW CREATE TABLE writes a literal: quotes doubled, a backslash and the control characters escaped.
     */
    public function quoted(string $text): string
    {
        return "'" . strtr($text, ['\\' => '\\\\', "'" => "''", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1A" => '\\Z']) . "'";
    }

    /**
     * Converts a string the column holds in its character set into utf8mb4.
     */
    public function utf8(string $text, Domain $domain): string
    {
        if ($domain->kind !== Kind::String || $domain->collation->bytes() || Encoding::utf8($domain->collation->charset)) {
            return $text;
        }

        return Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4'));
    }
}
