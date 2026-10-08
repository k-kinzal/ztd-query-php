<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of the JSON functions: JSON values, BIGINTs and utf8mb4_bin strings.
 *
 * The functions that answer a document are JSON. JSON_VALID, JSON_LENGTH, JSON_DEPTH,
 * JSON_CONTAINS, JSON_CONTAINS_PATH, JSON_STORAGE_SIZE and JSON_STORAGE_FREE are BIGINTs of 21
 * digits, JSON_OVERLAPS and JSON_SCHEMA_VALID of one digit. JSON_TYPE is a VARCHAR of 17
 * characters; JSON_QUOTE is six times as long as its argument and two more, JSON_UNQUOTE as long
 * as its argument, or a LONGTEXT for a JSON argument (of 16777216 characters in MySQL 5.7); JSON_PRETTY is a LONGTEXT; all are
 * coercible utf8mb4_bin strings. The schema functions are NULL for a NULL schema. A string longer than 16383 characters is a TEXT the server
 * counts in bytes, which it reports four times over again (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-function-reference.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class JsonResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $json = static fn (Invocation $call): Domain => self::json($call->derivation->context->profile->grammar);
        $count = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 21);
        $truth = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 1);
        $rules = [
            'JSON_TYPE' => static fn (Invocation $call): Domain => self::text(17),
            'JSON_QUOTE' => static fn (Invocation $call): Domain => self::text($call->length($call->domain(0)) * 6 + 2),
            'JSON_UNQUOTE' => static fn (Invocation $call): Domain => $call->domain(0)->kind === Kind::Json ? self::text($call->derivation->context->profile->grammar === GrammarRelease::MySql5744 ? 16777216 : 4294967295) : self::text($call->length($call->domain(0))),
            'JSON_PRETTY' => static fn (Invocation $call): Domain => self::text(16777216),
            'JSON_OVERLAPS' => $truth,
            'JSON_SCHEMA_VALID' => static fn (Invocation $call): Domain => $call->domain(0)->kind === Kind::Null ? Domain::null() : $truth($call),
        ];
        foreach (['JSON_ARRAY', 'JSON_OBJECT', 'JSON_EXTRACT', 'JSON_KEYS', 'JSON_SEARCH', 'JSON_SET', 'JSON_INSERT', 'JSON_REPLACE', 'JSON_REMOVE', 'JSON_ARRAY_APPEND', 'JSON_ARRAY_INSERT', 'JSON_APPEND', 'JSON_MERGE', 'JSON_MERGE_PATCH', 'JSON_MERGE_PRESERVE'] as $name) {
            $rules[$name] = $json;
        }
        $rules['JSON_SCHEMA_VALIDATION_REPORT'] = static fn (Invocation $call): Domain => $call->domain(0)->kind === Kind::Null ? Domain::null() : self::json($call->derivation->context->profile->grammar);
        foreach (['JSON_VALID', 'JSON_LENGTH', 'JSON_DEPTH', 'JSON_CONTAINS', 'JSON_CONTAINS_PATH', 'JSON_STORAGE_SIZE', 'JSON_STORAGE_FREE'] as $name) {
            $rules[$name] = $count;
        }

        return $rules;
    }

    /**
     * Answers the type of a JSON value: in MySQL 5.7 a binary JSON of 4194304 bytes, 16777216 for an aggregate (verified on a live 5.7.44 server).
     *
     * @param bool $aggregate Whether the value is that of an aggregate
     *
     * @example A JSON value
     *     \SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults::json(\SqlSemantics\Contract\GrammarRelease::MySql847)->field // => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json
     */
    public static function json(GrammarRelease $release = GrammarRelease::MySql847, bool $aggregate = false): Domain
    {
        if ($release === GrammarRelease::MySql5744) {
            return new Domain(Kind::Json, Field::Json, $aggregate ? 16777216 : 4194304, 0, false, Collation::binary());
        }

        return new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'));
    }

    /**
     * Answers a coercible utf8mb4_bin string of a length in characters: a VARCHAR up to 16383 characters, else a TEXT counted in bytes.
     *
     * @example A long string
     *     \SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults::text(16384)->length // => 65536
     */
    public static function text(int $length): Domain
    {
        if ($length <= 16383) {
            return Domain::string($length, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Coercible);
        }
        $bytes = min($length * 4, 4294967295);

        return Domain::string($bytes, Collation::known('utf8mb4_bin'), $bytes > 16777215 ? Field::LongBlob : Field::MediumBlob, Coercibility::Coercible);
    }
}
