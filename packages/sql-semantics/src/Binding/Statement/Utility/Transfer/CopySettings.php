<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Transfer;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Statement\Utility\OptionWords;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Loading\Copy;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Converts COPY option pairs to typed options as the server's option reader does: each option at most once, each argument in its domain.
 * @visibility SqlSemantics
 */
final class CopySettings
{
    /**
     * @param list<array{string, string|int|list<string>|true|null, Node}> $options
     * @throws InvalidSql
     */
    public static function options(array $options, Node $source): Copy\CopyOptions
    {
        $values = [];
        try {
            foreach ($options as [$name, $argument, $node]) {
                if (array_key_exists($name, $values)) {
                    throw new InvalidSql(InputViolation::CopyOption, $node);
                }
                $values[$name] = self::value($name, $argument) ?? throw new InvalidSql(InputViolation::CopyOption, $node);
            }
            return new Copy\CopyOptions(
                format: self::typed($values, 'format', Copy\CopyFormat::class) ?? Copy\CopyFormat::Text,
                freeze: ($values['freeze'] ?? false) === true,
                delimiter: self::text($values, 'delimiter'),
                null: self::text($values, 'null'),
                default: self::text($values, 'default'),
                header: self::typed($values, 'header', Copy\CopyHeader::class) ?? Copy\CopyHeader::Absent,
                quote: self::text($values, 'quote'),
                escape: self::text($values, 'escape'),
                forceQuote: self::choice($values, 'force_quote'),
                forceNotNull: self::choice($values, 'force_not_null'),
                forceNull: self::choice($values, 'force_null'),
                encoding: self::text($values, 'encoding'),
                onError: self::typed($values, 'on_error', Copy\CopyErrorAction::class),
                logVerbosity: self::typed($values, 'log_verbosity', Copy\CopyLogVerbosity::class) ?? Copy\CopyLogVerbosity::Default,
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CopyOption, $source, $error);
        }
    }

    /**
     * Converts one argument to its option's domain; null means the option is unknown or the argument is outside its domain.
     * @param string|int|list<string>|true|null $argument
     * @throws InvalidStructure
     */
    public static function value(string $name, string|int|array|bool|null $argument): string|bool|Copy\ColumnChoice|Copy\CopyFormat|Copy\CopyHeader|Copy\CopyErrorAction|Copy\CopyLogVerbosity|null
    {
        $text = is_string($argument) || is_int($argument) ? (string) $argument : null;
        return match ($name) {
            'format' => Copy\CopyFormat::tryFrom($text ?? ''),
            'freeze' => $argument === null ? true : (is_string($argument) || is_int($argument) ? OptionWords::boolean($argument) : null),
            'header' => self::header($argument),
            'delimiter', 'null', 'default', 'quote', 'escape', 'encoding' => self::string($argument),
            'force_quote', 'force_not_null', 'force_null' => self::columns($argument),
            'on_error' => Copy\CopyErrorAction::tryFrom(strtolower($text ?? '')),
            'log_verbosity' => Copy\CopyLogVerbosity::tryFrom(strtolower($text ?? '')),
            default => null,
        };
    }

    /**
     * Reads a string argument; a name list reads as its dotted names, as the server's string reader does.
     * @param string|int|list<string>|true|null $argument
     */
    public static function string(string|int|array|bool|null $argument): ?string
    {
        return is_string($argument) || is_int($argument) ? (string) $argument : (is_array($argument) ? implode('.', $argument) : null);
    }

    /**
     * Reads a FORCE option argument: an asterisk or a list of column names.
     * @param string|int|list<string>|true|null $argument
     * @throws InvalidStructure
     */
    public static function columns(string|int|array|bool|null $argument): ?Copy\ColumnChoice
    {
        return $argument === true ? new Copy\EveryColumn() : (is_array($argument) && $argument !== [] ? new Copy\ListedColumns($argument) : null);
    }

    /**
     * Reads HEADER: a Boolean argument or match, in any case.
     * @param string|int|list<string>|true|null $argument
     */
    public static function header(string|int|array|bool|null $argument): ?Copy\CopyHeader
    {
        if ($argument === null) {
            return Copy\CopyHeader::Present;
        }
        if (is_string($argument) && strtolower($argument) === 'match') {
            return Copy\CopyHeader::Match;
        }
        $flag = is_string($argument) || is_int($argument) ? OptionWords::boolean($argument) : null;
        return $flag === null ? null : ($flag ? Copy\CopyHeader::Present : Copy\CopyHeader::Absent);
    }

    /**
     * @template T of object
     * @param array<string, string|bool|Copy\ColumnChoice|Copy\CopyFormat|Copy\CopyHeader|Copy\CopyErrorAction|Copy\CopyLogVerbosity> $values
     * @param class-string<T> $class
     * @return T|null
     */
    public static function typed(array $values, string $name, string $class): ?object
    {
        $value = $values[$name] ?? null;
        return $value instanceof $class ? $value : null;
    }

    /**
     * @param array<string, string|bool|Copy\ColumnChoice|Copy\CopyFormat|Copy\CopyHeader|Copy\CopyErrorAction|Copy\CopyLogVerbosity> $values
     */
    public static function text(array $values, string $name): ?string
    {
        $value = $values[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, string|bool|Copy\ColumnChoice|Copy\CopyFormat|Copy\CopyHeader|Copy\CopyErrorAction|Copy\CopyLogVerbosity> $values
     */
    public static function choice(array $values, string $name): ?Copy\ColumnChoice
    {
        $value = $values[$name] ?? null;
        return $value instanceof Copy\ColumnChoice ? $value : null;
    }
}
