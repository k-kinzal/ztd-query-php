<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Identity\TemporalStorage;
use SqlSemantics\Type\Identity\TimeZoneMode;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Describes clock and session request results without reading their current values.
 * @visibility SqlSemantics
 */
final class ContextResult
{
    /**
     * Applies a clock precision to the request's declared temporal type.
     */
    public static function type(ContextValueKind $request, Dialect $dialect, ?int $precision): TypeDescriptor
    {
        $base = match ($request) {
            ContextValueKind::CurrentDate => BuiltinIdentity::Date,
            ContextValueKind::CurrentTime => $dialect === Dialect::PostgreSql ? BuiltinIdentity::Timetz : BuiltinIdentity::Time,
            ContextValueKind::CurrentTimestamp => $dialect === Dialect::PostgreSql ? BuiltinIdentity::Timestamptz : BuiltinIdentity::Timestamp,
            ContextValueKind::LocalTime => BuiltinIdentity::Time,
            ContextValueKind::LocalTimestamp => BuiltinIdentity::Timestamp,
            ContextValueKind::CurrentUser, ContextValueKind::SessionUser, ContextValueKind::SystemUser, ContextValueKind::User, ContextValueKind::CurrentRole, ContextValueKind::CurrentSchema, ContextValueKind::CurrentCatalog => BuiltinIdentity::Text,
        };
        if ($precision === null) {
            return new TypeDescriptor($dialect, $base);
        }
        $withZone = in_array($base, [BuiltinIdentity::Timetz, BuiltinIdentity::Timestamptz], true);
        $storage = match ($base) {
            BuiltinIdentity::Timetz => BuiltinIdentity::Time,
            BuiltinIdentity::Timestamptz => BuiltinIdentity::Timestamp,
            BuiltinIdentity::Date, BuiltinIdentity::Time, BuiltinIdentity::Timestamp, BuiltinIdentity::Text => $base,
        };
        return new TypeDescriptor($dialect, new TemporalStorage($storage, new NumericParameter((string) $precision), $withZone ? TimeZoneMode::With : TimeZoneMode::Unspecified));
    }
}
