<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Column as C;
use SqlSemantics\Platform\MySql\Statement\Type as T;
use SqlSemantics\Platform\MySql\Statement\Type\Kind as K;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Infers declarations for computed CREATE TABLE ... SELECT fields from resolved types.
 *
 * These are semantic column values, independent of SQL spelling and session quote
 * modes. Direct input columns can instead retain their declared defaults and types.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html.
 *
 * @visibility SqlSemantics
 */
final class QueryColumns
{
    /**
     * @param GrammarRelease $release The declaration rules of the target release
     * @param bool $strict Whether inferred non-null temporal fields require explicit values
     */
    public function __construct(public readonly GrammarRelease $release, public readonly bool $strict = true)
    {
    }

    /**
     * Builds a column without rendering or reparsing any SQL.
     */
    public function column(Name $name, Domain $domain, bool $nullable): C\ColumnDefinition
    {
        $attributes = [];
        if (!$domain->collation->bytes() && in_array($domain->field, [Field::Enum, Field::Set, Field::VarChar, Field::VarString, Field::String, Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true)) {
            $attributes[] = new C\CollateAttribute(new CollationName(new Name($domain->collation->name)));
        }
        if (!$nullable && $domain->field !== Field::Null) {
            $attributes[] = new C\KeywordAttribute(C\Kind\ColumnKeyword::NotNull);
            $default = $this->default($domain);
            if ($default !== null) {
                $attributes[] = new C\DefaultLiteral(new StringLiteral([$default]));
            }
        }

        return new C\ColumnDefinition(new ColumnName($name), new C\OrdinaryColumn($this->type($domain), $attributes));
    }

    /**
     * Answers the field used for storage, including YEAR expressions reported as binary strings.
     */
    public function field(Domain $domain): Field
    {
        return $domain->storageField ?? ($domain->kind === Kind::Year && $domain->field === Field::VarString && $domain->collation->bytes() ? Field::LongLong : $domain->field);
    }

    /**
     * Retains literal, reference and set-output DOUBLE widths; ordinary modern
     * computed fields use the DOUBLE display width. A null expression denotes a
     * star or set output. Unary plus and grouping retain the operand's role.
     */
    public function width(Domain $domain, ?Scalar $expression, bool $copied = false): int
    {
        if (in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) || $copied) {
            return $domain->display ?? $domain->length;
        }
        while ($expression instanceof Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus)) {
            $expression = $expression->operand;
        }

        return $expression === null || $expression instanceof NumberLiteral || $expression instanceof ColumnUse ? $domain->length : 22;
    }

    /**
     * Infers the complete storage type, including widths, scales and character sets.
     */
    public function type(Domain $domain): T\DeclaredName
    {
        $field = $this->field($domain);
        $unsigned = $domain->unsigned ? [K\NumericModifier::Unsigned] : [];
        $fraction = $domain->decimals > 0 && $domain->decimals < Domain::NOT_FIXED ? (string) $domain->decimals : null;

        return match ($field) {
            Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong => $this->integer($domain),
            Field::Decimal, Field::NewDecimal => new T\Decimal((string) $domain->precision(), (string) $domain->decimals, $unsigned),
            Field::Float, Field::Double => $this->floating($domain),
            Field::Null => new T\Binary(in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044], true) ? K\BinaryKind::Binary : K\BinaryKind::VarBinary, '0'),
            Field::Date, Field::NewDate => new T\Temporal(K\TemporalKind::Date),
            Field::Time => new T\Temporal(K\TemporalKind::Time, $fraction),
            Field::DateTime => new T\Temporal(K\TemporalKind::DateTime, $fraction),
            Field::Timestamp => new T\Temporal(K\TemporalKind::Timestamp, $fraction),
            Field::Year => new T\Temporal(K\TemporalKind::Year),
            Field::Bit => new T\Elementary(K\ElementaryKind::Bit, (string) max(1, $domain->length)),
            Field::Json => new T\Elementary(K\ElementaryKind::Json),
            Field::Vector => new T\Elementary(K\ElementaryKind::Vector, (string) max(1, intdiv($domain->length, 4))),
            Field::Geometry => new T\Spatial(K\SpatialKind::Geometry),
            Field::Enum, Field::Set, Field::VarChar, Field::VarString, Field::String, Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob => $this->character($domain),
        };
    }

    /**
     * Keeps fixed floating scales and release-specific DOUBLE display widths.
     */
    public function floating(Domain $domain): T\Floating
    {
        $kind = $this->field($domain) === Field::Float ? K\FloatingKind::Float : K\FloatingKind::Double;
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $length = $kind === K\FloatingKind::Double && !$legacy ? 22 : $domain->length;
        $fixed = $domain->decimals < Domain::NOT_FIXED;

        return new T\Floating($kind, $fixed ? (string) $length : null, $fixed ? (string) $domain->decimals : null, $domain->unsigned ? [K\NumericModifier::Unsigned] : []);
    }

    /**
     * Narrows computed integers by display length while preserving explicit widths.
     */
    public function integer(Domain $domain): T\Integral
    {
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $field = $this->field($domain);
        \SqlSemantics\Diagnostic\Check::input(in_array($field, [Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong], true), 'An inferred integer declaration requires an integer storage field.');
        $kind = match ($field) {
            Field::Tiny => K\IntegralKind::TinyInt,
            Field::Short => K\IntegralKind::SmallInt,
            Field::Int24 => K\IntegralKind::MediumInt,
            Field::LongLong => $domain->length >= ($legacy ? 10 : 11) ? K\IntegralKind::BigInt : K\IntegralKind::Int,
            Field::Long => K\IntegralKind::Int,
        };

        return new T\Integral($kind, (string) ($domain->display ?? $domain->length), $domain->unsigned ? [K\NumericModifier::Unsigned] : []);
    }

    /**
     * Preserves enumeration members as decoded values and sizes strings by their byte capacity.
     */
    public function character(Domain $domain): T\DeclaredName
    {
        $binary = $domain->collation->bytes();
        $charset = $binary ? null : new T\CharsetAttribute(K\CharsetForm::CharacterSet, new Name($domain->collation->charset->name));
        if ($domain->field === Field::Enum || $domain->field === Field::Set) {
            return new T\Enumeration($domain->field === Field::Enum ? K\EnumerationKind::Enum : K\EnumerationKind::Set, array_map(static fn (string $member): Text => new Text($member), $domain->members), $charset);
        }
        if ($binary && $domain->field === Field::String && $domain->length === 0) {
            return new T\Binary(K\BinaryKind::Binary, '0');
        }
        $blob = $domain->field->blob();
        if (!$blob && $domain->length <= 512) {
            return $binary ? new T\Binary(K\BinaryKind::VarBinary, (string) $domain->length) : new T\Character(K\CharacterKind::VarChar, (string) $domain->length, charset: $charset);
        }
        $bytes = $blob && $binary ? $domain->length : $domain->length * $domain->collation->charset->maxLength;
        $size = match (true) {
            $bytes < 256 => 'TINY',
            $bytes < 65536 => '',
            $bytes < 16777216 => 'MEDIUM',
            default => 'LONG',
        };

        return $binary ? new T\Binary(K\BinaryKind::from($size . 'BLOB')) : new T\Character(K\CharacterKind::from($size . 'TEXT'), charset: $charset);
    }

    /**
     * Answers the implicit value retained as a default for a computed NOT NULL column.
     */
    public function default(Domain $domain): ?string
    {
        if (($this->release === GrammarRelease::MySql5651 || !$this->strict) && in_array($domain->kind, [Kind::Date, Kind::DateTime], true)) {
            $fraction = $domain->decimals > 0 && $domain->decimals < Domain::NOT_FIXED ? '.' . str_repeat('0', $domain->decimals) : '';

            return $domain->kind === Kind::Date ? '0000-00-00' : '0000-00-00 00:00:00' . $fraction;
        }
        return match ($domain->kind) {
            Kind::Integer, Kind::Double, Kind::Year => '0',
            Kind::Decimal => '0' . ($domain->decimals > 0 ? '.' . str_repeat('0', $domain->decimals) : ''),
            Kind::Time => '00:00:00' . ($domain->decimals > 0 && $domain->decimals < Domain::NOT_FIXED ? '.' . str_repeat('0', $domain->decimals) : ''),
            Kind::String => $domain->length > 512 || in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob, Field::Geometry, Field::Enum, Field::Set, Field::Vector], true) ? null : '',
            Kind::Date, Kind::DateTime, Kind::Json, Kind::Bit, Kind::Null => null,
        };
    }
}
