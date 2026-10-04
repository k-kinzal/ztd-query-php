<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

/**
 * The documented signatures of the `pg_catalog` functions whose results the model types.
 *
 * Rule: PG-CALL-SIGNATURES-001. Each row is one `pg_proc` entry of the
 * manual: the result type, the kind (s scalar, a aggregate, w window), the
 * NULL rule (P NULL exactly when an argument is: the function is strict; N
 * never NULL; Y possibly NULL: an aggregate over no row, or a non-strict
 * function) and the parameter types. Types are catalog names; `x[]` is the
 * array of `x`. Only functions whose parameters are concrete types are listed:
 * a call of a polymorphic or `"any"` parameter is never an exact match, so a
 * routine of the user can always win it. Functions not in the table are typed
 * as calls of undeclared routines.
 * Source: https://www.postgresql.org/docs/17/functions-string.html, https://www.postgresql.org/docs/17/functions-binarystring.html,
 * https://www.postgresql.org/docs/17/functions-bitstring.html, https://www.postgresql.org/docs/17/functions-math.html,
 * https://www.postgresql.org/docs/17/functions-formatting.html, https://www.postgresql.org/docs/17/functions-datetime.html,
 * https://www.postgresql.org/docs/17/functions-json.html, https://www.postgresql.org/docs/17/functions-xml.html,
 * https://www.postgresql.org/docs/17/functions-info.html, https://www.postgresql.org/docs/17/functions-aggregate.html,
 * https://www.postgresql.org/docs/17/functions-window.html, https://www.postgresql.org/docs/17/functions-uuid.html.
 * Termination: constant work. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Signatures
{
    /**
     * The aggregates over one numeric argument and their result types by argument type.
     */
    private const NUMERIC_AGGREGATES = [
        'sum' => ['int2' => 'int8', 'int4' => 'int8', 'int8' => 'numeric', 'float4' => 'float4', 'float8' => 'float8', 'numeric' => 'numeric', 'interval' => 'interval', 'money' => 'money'],
        'avg' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric', 'interval' => 'interval'],
        'stddev' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
        'stddev_pop' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
        'stddev_samp' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
        'variance' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
        'var_pop' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
        'var_samp' => ['int2' => 'numeric', 'int4' => 'numeric', 'int8' => 'numeric', 'float4' => 'float8', 'float8' => 'float8', 'numeric' => 'numeric'],
    ];

    /**
     * The types `min` and `max` are defined for, returning the argument type.
     */
    private const ORDERED = ['int2', 'int4', 'int8', 'oid', 'float4', 'float8', 'numeric', 'money', 'text', 'bpchar', 'date', 'time', 'timetz', 'timestamp', 'timestamptz', 'interval', 'inet', 'pg_lsn', 'tid'];

    /**
     * The scalar and window functions: rows of result, kind, NULL rule and parameter types.
     */
    private const FUNCTIONS = [
        'upper' => [['text', 's', 'P', 'text']],
        'lower' => [['text', 's', 'P', 'text']],
        'initcap' => [['text', 's', 'P', 'text']],
        'length' => [['int4', 's', 'P', 'text'], ['int4', 's', 'P', 'bpchar'], ['int4', 's', 'P', 'bytea'], ['int4', 's', 'P', 'bit'], ['int4', 's', 'P', 'tsvector'], ['int4', 's', 'P', 'text', 'name'], ['int4', 's', 'P', 'bytea', 'name']],
        'char_length' => [['int4', 's', 'P', 'text'], ['int4', 's', 'P', 'bpchar']],
        'character_length' => [['int4', 's', 'P', 'text'], ['int4', 's', 'P', 'bpchar']],
        'octet_length' => [['int4', 's', 'P', 'text'], ['int4', 's', 'P', 'bpchar'], ['int4', 's', 'P', 'bytea'], ['int4', 's', 'P', 'bit']],
        'bit_length' => [['int4', 's', 'P', 'text'], ['int4', 's', 'P', 'bytea'], ['int4', 's', 'P', 'bit']],
        'ascii' => [['int4', 's', 'P', 'text']],
        'chr' => [['text', 's', 'P', 'int4']],
        'repeat' => [['text', 's', 'P', 'text', 'int4']],
        'reverse' => [['text', 's', 'P', 'text']],
        'left' => [['text', 's', 'P', 'text', 'int4']],
        'right' => [['text', 's', 'P', 'text', 'int4']],
        'lpad' => [['text', 's', 'P', 'text', 'int4'], ['text', 's', 'P', 'text', 'int4', 'text']],
        'rpad' => [['text', 's', 'P', 'text', 'int4'], ['text', 's', 'P', 'text', 'int4', 'text']],
        'btrim' => [['text', 's', 'P', 'text'], ['text', 's', 'P', 'text', 'text'], ['bytea', 's', 'P', 'bytea', 'bytea']],
        'ltrim' => [['text', 's', 'P', 'text'], ['text', 's', 'P', 'text', 'text'], ['bytea', 's', 'P', 'bytea', 'bytea']],
        'rtrim' => [['text', 's', 'P', 'text'], ['text', 's', 'P', 'text', 'text'], ['bytea', 's', 'P', 'bytea', 'bytea']],
        'replace' => [['text', 's', 'P', 'text', 'text', 'text']],
        'split_part' => [['text', 's', 'P', 'text', 'text', 'int4']],
        'strpos' => [['int4', 's', 'P', 'text', 'text']],
        'starts_with' => [['bool', 's', 'P', 'text', 'text']],
        'translate' => [['text', 's', 'P', 'text', 'text', 'text']],
        'md5' => [['text', 's', 'P', 'text'], ['text', 's', 'P', 'bytea']],
        'quote_ident' => [['text', 's', 'P', 'text']],
        'quote_literal' => [['text', 's', 'P', 'text']],
        'to_hex' => [['text', 's', 'P', 'int4'], ['text', 's', 'P', 'int8']],
        'substr' => [['text', 's', 'P', 'text', 'int4'], ['text', 's', 'P', 'text', 'int4', 'int4'], ['bytea', 's', 'P', 'bytea', 'int4'], ['bytea', 's', 'P', 'bytea', 'int4', 'int4']],
        'substring' => [
            ['text', 's', 'P', 'text', 'int4', 'int4'], ['text', 's', 'P', 'text', 'int4'], ['text', 's', 'P', 'text', 'text'], ['text', 's', 'P', 'text', 'text', 'text'],
            ['bytea', 's', 'P', 'bytea', 'int4', 'int4'], ['bytea', 's', 'P', 'bytea', 'int4'], ['bit', 's', 'P', 'bit', 'int4', 'int4'], ['bit', 's', 'P', 'bit', 'int4'],
        ],
        'overlay' => [
            ['text', 's', 'P', 'text', 'text', 'int4', 'int4'], ['text', 's', 'P', 'text', 'text', 'int4'], ['bytea', 's', 'P', 'bytea', 'bytea', 'int4', 'int4'],
            ['bytea', 's', 'P', 'bytea', 'bytea', 'int4'], ['bit', 's', 'P', 'bit', 'bit', 'int4', 'int4'], ['bit', 's', 'P', 'bit', 'bit', 'int4'],
        ],
        'position' => [['int4', 's', 'P', 'text', 'text'], ['int4', 's', 'P', 'bytea', 'bytea'], ['int4', 's', 'P', 'bit', 'bit']],
        'normalize' => [['text', 's', 'P', 'text'], ['text', 's', 'P', 'text', 'text']],
        'extract' => [['numeric', 's', 'P', 'text', 'date'], ['numeric', 's', 'P', 'text', 'time'], ['numeric', 's', 'P', 'text', 'timetz'], ['numeric', 's', 'P', 'text', 'timestamp'], ['numeric', 's', 'P', 'text', 'timestamptz'], ['numeric', 's', 'P', 'text', 'interval']],
        'date_part' => [['float8', 's', 'P', 'text', 'date'], ['float8', 's', 'P', 'text', 'time'], ['float8', 's', 'P', 'text', 'timetz'], ['float8', 's', 'P', 'text', 'timestamp'], ['float8', 's', 'P', 'text', 'timestamptz'], ['float8', 's', 'P', 'text', 'interval']],
        'date_trunc' => [['timestamp', 's', 'P', 'text', 'timestamp'], ['timestamptz', 's', 'P', 'text', 'timestamptz'], ['interval', 's', 'P', 'text', 'interval'], ['timestamptz', 's', 'P', 'text', 'timestamptz', 'text']],
        'age' => [['interval', 's', 'P', 'timestamp'], ['interval', 's', 'P', 'timestamp', 'timestamp'], ['interval', 's', 'P', 'timestamptz'], ['interval', 's', 'P', 'timestamptz', 'timestamptz']],
        'now' => [['timestamptz', 's', 'N']],
        'transaction_timestamp' => [['timestamptz', 's', 'N']],
        'statement_timestamp' => [['timestamptz', 's', 'N']],
        'clock_timestamp' => [['timestamptz', 's', 'N']],
        'timeofday' => [['text', 's', 'N']],
        'make_date' => [['date', 's', 'P', 'int4', 'int4', 'int4']],
        'make_time' => [['time', 's', 'P', 'int4', 'int4', 'float8']],
        'to_char' => [['text', 's', 'P', 'timestamp', 'text'], ['text', 's', 'P', 'timestamptz', 'text'], ['text', 's', 'P', 'interval', 'text'], ['text', 's', 'P', 'int4', 'text'], ['text', 's', 'P', 'int8', 'text'], ['text', 's', 'P', 'numeric', 'text'], ['text', 's', 'P', 'float4', 'text'], ['text', 's', 'P', 'float8', 'text']],
        'to_date' => [['date', 's', 'P', 'text', 'text']],
        'to_timestamp' => [['timestamptz', 's', 'P', 'text', 'text'], ['timestamptz', 's', 'P', 'float8']],
        'to_number' => [['numeric', 's', 'P', 'text', 'text']],
        'isfinite' => [['bool', 's', 'P', 'date'], ['bool', 's', 'P', 'timestamp'], ['bool', 's', 'P', 'timestamptz'], ['bool', 's', 'P', 'interval']],
        'abs' => [['int2', 's', 'P', 'int2'], ['int4', 's', 'P', 'int4'], ['int8', 's', 'P', 'int8'], ['float4', 's', 'P', 'float4'], ['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'ceil' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'ceiling' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'floor' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'round' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric'], ['numeric', 's', 'P', 'numeric', 'int4']],
        'trunc' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric'], ['numeric', 's', 'P', 'numeric', 'int4']],
        'sqrt' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'cbrt' => [['float8', 's', 'P', 'float8']],
        'exp' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'ln' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'log' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'log10' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'power' => [['float8', 's', 'P', 'float8', 'float8'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'pow' => [['float8', 's', 'P', 'float8', 'float8'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'mod' => [['int2', 's', 'P', 'int2', 'int2'], ['int4', 's', 'P', 'int4', 'int4'], ['int8', 's', 'P', 'int8', 'int8'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'div' => [['numeric', 's', 'P', 'numeric', 'numeric']],
        'gcd' => [['int4', 's', 'P', 'int4', 'int4'], ['int8', 's', 'P', 'int8', 'int8'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'lcm' => [['int4', 's', 'P', 'int4', 'int4'], ['int8', 's', 'P', 'int8', 'int8'], ['numeric', 's', 'P', 'numeric', 'numeric']],
        'sign' => [['float8', 's', 'P', 'float8'], ['numeric', 's', 'P', 'numeric']],
        'factorial' => [['numeric', 's', 'P', 'int8']],
        'degrees' => [['float8', 's', 'P', 'float8']],
        'radians' => [['float8', 's', 'P', 'float8']],
        'sin' => [['float8', 's', 'P', 'float8']],
        'cos' => [['float8', 's', 'P', 'float8']],
        'tan' => [['float8', 's', 'P', 'float8']],
        'pi' => [['float8', 's', 'N']],
        'random' => [['float8', 's', 'N']],
        'width_bucket' => [['int4', 's', 'P', 'float8', 'float8', 'float8', 'int4'], ['int4', 's', 'P', 'numeric', 'numeric', 'numeric', 'int4']],
        'version' => [['text', 's', 'N']],
        'current_database' => [['name', 's', 'N']],
        'pg_backend_pid' => [['int4', 's', 'N']],
        'gen_random_uuid' => [['uuid', 's', 'N']],
        'current_setting' => [['text', 's', 'P', 'text']],
        'json_typeof' => [['text', 's', 'P', 'json']],
        'jsonb_typeof' => [['text', 's', 'P', 'jsonb']],
        'jsonb_pretty' => [['text', 's', 'P', 'jsonb']],
        'json_array_length' => [['int4', 's', 'P', 'json']],
        'jsonb_array_length' => [['int4', 's', 'P', 'jsonb']],
        'json_strip_nulls' => [['json', 's', 'P', 'json']],
        'jsonb_strip_nulls' => [['jsonb', 's', 'P', 'jsonb']],
        'json_object' => [['json', 's', 'P', 'text[]'], ['json', 's', 'P', 'text[]', 'text[]']],
        'xmlexists' => [['bool', 's', 'P', 'text', 'xml']],
        'pg_collation_for' => [['text', 's', 'Y', 'any']],
        'system_user' => [['text', 's', 'Y']],
        'count' => [['int8', 'a', 'N']],
        'bool_and' => [['bool', 'a', 'Y', 'bool']],
        'bool_or' => [['bool', 'a', 'Y', 'bool']],
        'every' => [['bool', 'a', 'Y', 'bool']],
        'string_agg' => [['text', 'a', 'Y', 'text', 'text'], ['bytea', 'a', 'Y', 'bytea', 'bytea']],
        'bit_and' => [['int2', 'a', 'Y', 'int2'], ['int4', 'a', 'Y', 'int4'], ['int8', 'a', 'Y', 'int8'], ['bit', 'a', 'Y', 'bit']],
        'bit_or' => [['int2', 'a', 'Y', 'int2'], ['int4', 'a', 'Y', 'int4'], ['int8', 'a', 'Y', 'int8'], ['bit', 'a', 'Y', 'bit']],
        'xmlagg' => [['xml', 'a', 'Y', 'xml']],
        'corr' => [['float8', 'a', 'Y', 'float8', 'float8']],
        'covar_pop' => [['float8', 'a', 'Y', 'float8', 'float8']],
        'covar_samp' => [['float8', 'a', 'Y', 'float8', 'float8']],
        'row_number' => [['int8', 'w', 'N']],
        'rank' => [['int8', 'w', 'N']],
        'dense_rank' => [['int8', 'w', 'N']],
        'percent_rank' => [['float8', 'w', 'N']],
        'cume_dist' => [['float8', 'w', 'N']],
        'ntile' => [['int4', 'w', 'Y', 'int4']],
    ];

    /**
     * Answers the rows of a function name: result, kind, NULL rule and parameter types.
     *
     * @return list<array{string, string, string, list<string>}>
     */
    public function rows(string $name): array
    {
        $rows = [];
        foreach (self::FUNCTIONS[$name] ?? [] as $row) {
            $rows[] = [$row[0], $row[1], $row[2], array_slice($row, 3)];
        }
        foreach (self::NUMERIC_AGGREGATES[$name] ?? [] as $argument => $result) {
            $rows[] = [$result, 'a', 'Y', [$argument]];
        }
        if ($name === 'min' || $name === 'max') {
            foreach (self::ORDERED as $argument) {
                $rows[] = [$argument, 'a', 'Y', [$argument]];
            }
        }

        return $rows;
    }
}
