<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use Closure;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Pattern\Patterns;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Function\Text\Soundex;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Operator\Weight;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Call\CharCall;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Compiles the string forms written with keywords: COLLATE, BINARY, CONVERT ... USING, TRIM, POSITION, CHAR, SOUNDS LIKE and REGEXP.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Texts
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers a call of an inline routine over compiled arguments.
     *
     * @param list<Evaluable> $arguments
     * @param Closure(Frame, list<Evaluable>, Domain): (int|float|string|null) $body Computes the result
     */
    public function call(string $name, array $arguments, Domain $domain, Closure $body): Call
    {
        return new Call(new Routine($name, count($arguments), count($arguments), $body), $arguments, $domain);
    }

    /**
     * Compiles `expr COLLATE name`: the string with an explicit collation of its character set.
     *
     * @throws \MySqlMemory\Error\SqlError When the collation is unknown or not of the character set
     */
    public function collated(Collated $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $collation = Collation::named($node->collation->value);
        if ($collation === null) {
            throw SchemaError::UnknownCollation->error($node->collation->value);
        }
        $domain = $operand->domain();
        $charset = $domain->kind === Kind::String ? $domain->collation->charset : $this->compiler->settings->connectionCollation->charset;
        if ($collation->charset !== $charset) {
            throw SchemaError::CollationCharsetMismatch->error($collation->name, $charset->name);
        }
        $length = $domain->kind === Kind::String ? $domain->length : (new Strings())->length($domain);
        $result = $this->compiler->domain($node);

        return $this->call('COLLATE', [$operand], $result, static fn (Frame $f, array $a): ?string => Convert::toText($a[0]->evaluate($f), $a[0]->domain()));
    }

    /**
     * Compiles `BINARY expr`: the value as a binary string.
     */
    public function binary(BinaryCast $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $domain = $operand->domain();
        $length = $domain->kind === Kind::String ? $domain->length * $domain->collation->charset->maxLength : (new Strings())->length($domain);
        $result = $this->compiler->domain($node);

        return $this->call('BINARY', [$operand], $result, static fn (Frame $f, array $a): ?string => Convert::toText($a[0]->evaluate($f), $a[0]->domain()));
    }

    /**
     * Compiles `CONVERT(expr USING charset)`.
     *
     * @throws \MySqlMemory\Error\SqlError When the character set is unknown
     */
    public function convert(CharsetConversion $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $name = $node->charset->name->value ?? 'binary';
        $charset = Charset::named($name);
        if ($charset === null) {
            throw SchemaError::UnknownCharacterSet->error($name);
        }
        $domain = $operand->domain();
        $length = $domain->kind === Kind::String ? $domain->length : (new Strings())->length($domain);
        $collation = $charset->defaultCollation($this->compiler->settings->release());
        $result = $this->compiler->domain($node);

        return $this->call('CONVERT', [$operand], $result, static function (Frame $f, array $a) use ($charset): ?string {
            $text = Convert::toText($a[0]->evaluate($f), $a[0]->domain());

            return $text === null ? null : Conversion::transcode($text, $a[0]->domain(), $charset, $f->context);
        });
    }

    /**
     * Compiles TRIM([BOTH | LEADING | TRAILING] [remstr] FROM str).
     */
    public function trim(Trim $node, Scope $scope): Evaluable
    {
        $subject = $this->compiler->compile($node->subject, $scope);
        $removed = $node->removed === null ? null : $this->compiler->compile($node->removed, $scope);
        $arguments = $removed === null ? [$subject] : [$subject, $removed];
        $result = $this->compiler->domain($node);
        $side = $node->side ?? TrimSide::Both;

        return $this->call('TRIM', $arguments, $result, static function (Frame $f, array $a, Domain $r) use ($side): ?string {
            $strings = new Strings();
            $text = $strings->text($f, $a[0], $r);
            $cut = isset($a[1]) ? $strings->text($f, $a[1], $r) : Encoding::convert(' ', Charset::known('ascii'), $r->collation->charset);
            if ($text === null || $cut === null) {
                return null;
            }
            if ($cut === '') {
                return $text;
            }
            if ($side !== TrimSide::Trailing) {
                while (str_starts_with($text, $cut)) {
                    $text = substr($text, strlen($cut));
                }
            }
            if ($side !== TrimSide::Leading) {
                while ($text !== '' && str_ends_with($text, $cut)) {
                    $text = substr($text, 0, -strlen($cut));
                }
            }

            return $text;
        });
    }

    /**
     * Compiles POSITION(substr IN str).
     */
    public function position(Position $node, Scope $scope): Evaluable
    {
        return $this->compiler->calls->named('LOCATE', [$node->substring, $node->string], $scope, $node);
    }

    /**
     * Compiles CHAR(n, ... [USING charset]): the bytes of each integer, NULL arguments skipped.
     */
    public function char(CharCall $node, Scope $scope): Evaluable
    {
        $arguments = array_map(fn ($argument): Evaluable => $this->compiler->compile($argument, $scope), $node->arguments);
        $collation = $node->charset === null ? Collation::binary() : (Charset::named($node->charset->name->value ?? 'binary')?->defaultCollation($this->compiler->settings->release()) ?? Collation::binary());
        $result = $this->compiler->domain($node);

        $charset = $collation->charset;

        return $this->call('CHAR', $arguments, $result, static function (Frame $f, array $a) use ($charset): ?string {
            $bytes = '';
            foreach ($a as $argument) {
                $value = Convert::toInteger($argument->evaluate($f), $argument->domain(), $f->context, true);
                if ($value === null) {
                    continue;
                }
                $chunk = '';
                for ($v = $value & 0xFFFFFFFF; $v > 0; $v >>= 8) {
                    $chunk = chr($v & 0xFF) . $chunk;
                }
                $bytes .= $chunk === '' ? "\0" : $chunk;
            }

            return Texts::characters($bytes, $charset, $f);
        });
    }

    /**
     * Takes the bytes CHAR writes as characters of a character set.
     *
     * The bytes are padded with leading zero bytes to the width of a UCS-2, UTF-16 or UTF-32
     * unit. Bytes that are no character warn (ER_INVALID_CHARACTER_STRING), quoting up to three
     * of them from the first; a single-byte set keeps them, a multibyte one answers NULL under a
     * strict SQL mode and the characters before them otherwise (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public static function characters(string $bytes, Charset $charset, Frame $frame): ?string
    {
        if ($charset === Charset::binary()) {
            return $bytes;
        }
        $unit = match ($charset->name) {
            'ucs2', 'utf16', 'utf16le' => 2,
            'utf32' => 4,
            default => 1,
        };
        $bytes = strlen($bytes) % $unit === 0 ? $bytes : str_repeat("\0", $unit - strlen($bytes) % $unit) . $bytes;
        if (Encoding::valid($bytes, $charset)) {
            return $bytes;
        }
        $valid = Encoding::prefix($bytes, $charset);
        $frame->context->warning(DataError::InvalidCharacterString, $charset->name, strtoupper(bin2hex(substr($bytes, $valid, 3))));
        if ($charset->maxLength === 1) {
            return $bytes;
        }

        return $frame->context->modes->strict() ? null : substr($bytes, 0, $valid);
    }

    /**
     * Compiles `a SOUNDS LIKE b`: SOUNDEX(a) = SOUNDEX(b), compared in the collation of both (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the collations do not mix
     */
    public function soundsLike(SoundsLike $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->operand, $scope);
        $right = $this->compiler->compile($node->pattern, $scope);
        $domain = $this->compiler->domain($node);
        [$collation] = Collations::aggregate([$left->domain(), $right->domain()], '=', $this->compiler->settings->connectionCollation, true);

        return $this->call('SOUNDS LIKE', [$left, $right], $domain, static function (Frame $f, array $a) use ($collation): ?int {
            $codes = [];
            foreach ($a as $argument) {
                $text = Convert::toText($argument->evaluate($f), $argument->domain());
                if ($text === null) {
                    return null;
                }
                $charset = (new Strings())->charset($argument->domain());
                $codes[] = Encoding::convert((new Soundex())->code($text, $charset), $charset, $collation->charset);
            }

            return Ordering::of($collation)->compare($codes[0], $codes[1]) === 0 ? 1 : 0;
        });
    }

    /**
     * Compiles `a [NOT] REGEXP b`: whether a matches the regular expression b, in the collation of both.
     *
     * A binary string matched with a string of another character set, either way, is an error
     * (ER_CHARACTER_SET_MISMATCH) that names the binary side 'binary' and the other by its
     * collation; a value that is no string mixes with either (verified on a live 8.4 server).
     * MySQL 8.0 and later match as REGEXP_LIKE does, with ICU (see Patterns); MySQL 5.6 and 5.7
     * match with the Henry Spencer library, approximated here by PCRE, and match a binary string
     * with any other byte by byte (verified on live 5.6.51 and 5.7.44 servers).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#operator_regexp.
     *
     * @throws \MySqlMemory\Error\SqlError When the collations do not mix
     */
    public function regexp(Regexp $node, Scope $scope): Evaluable
    {
        $subject = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $sides = array_map(static fn (Domain $domain): ?string => $domain->kind !== Kind::String ? null : ($domain->collation->charset === Charset::binary() ? 'binary' : $domain->collation->name), [$subject->domain(), $pattern->domain()]);
        if ($sides[0] !== null && $sides[1] !== null && ($sides[0] === 'binary') !== ($sides[1] === 'binary') && !$this->compiler->settings->legacy()) {
            throw DataError::CharacterSetMismatch->error($sides[0], $sides[1], 'regexp_like');
        }
        [$collation] = Collations::aggregate([$subject->domain(), $pattern->domain()], 'regexp_like', $this->compiler->settings->connectionCollation, true);
        $negated = $node->negated;
        $domain = $this->compiler->domain($node);
        if (!$this->compiler->settings->legacy()) {
            $patterns = new Patterns();

            return $this->call('REGEXP', [$subject, $pattern], $domain, static function (Frame $f, array $a) use ($patterns, $negated): ?int {
                $matched = $patterns->like($f, $a);

                return $matched === null ? null : (($matched === 1) !== $negated ? 1 : 0);
            });
        }

        $binary = $collation->charset === Charset::binary() || in_array('binary', $sides, true);
        $domain = $domain->withNullable($this->unmatched($node, $subject, $pattern));

        return $this->call('REGEXP', [$subject, $pattern], $domain, static function (Frame $f, array $a) use ($collation, $binary, $negated): ?int {
            $text = Convert::toText($a[0]->evaluate($f), $a[0]->domain());
            $expression = Convert::toText($a[1]->evaluate($f), $a[1]->domain());
            if ($text === null || $expression === null) {
                return null;
            }
            $strings = new Strings();
            $bytes = $binary;
            $text = $bytes ? $text : Encoding::convert($text, $strings->charset($a[0]->domain()), Charset::known('utf8mb4'));
            $expression = $bytes ? $expression : Encoding::convert($expression, $strings->charset($a[1]->domain()), Charset::known('utf8mb4'));
            $flags = $bytes ? '' : ($collation->binaryOrder() || str_ends_with($collation->name, '_cs') ? 'u' : 'ui');
            set_error_handler(static fn (): bool => true);
            try {
                $matched = preg_match('/' . str_replace('/', '\\/', $expression) . '/' . $flags, $text);
            } finally {
                restore_error_handler();
            }
            if ($matched === false) {
                throw DataError::RegexpError->error('The regular expression is not valid.');
            }

            return ($matched === 1) !== $negated ? 1 : 0;
        });
    }

    /**
     * Answers whether a match of MySQL 5.6 or 5.7 can be NULL, which they decide when they resolve the statement.
     *
     * The match can be NULL when the matched expression can; when the pattern varies by row, or
     * holds a subquery that reads a table; or when the value of a constant pattern, which the
     * server evaluates then, is NULL. A user variable, a system variable, a parameter and an
     * account function are constant (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the pattern is an error
     */
    public function unmatched(Regexp $node, Evaluable $subject, Evaluable $pattern): bool
    {
        $constancy = $this->compiler->constancy($node->pattern);
        if ($subject->domain()->nullable || $constancy === Constancy::Row) {
            return true;
        }
        if (!$pattern->domain()->nullable) {
            return false;
        }
        if ($constancy === Constancy::Statement && (new Walker())->find($node->pattern, Query::class) !== []) {
            return true;
        }

        return $pattern->evaluate(new Frame($this->compiler->connection->context)) === null;
    }

    /**
     * Compiles WEIGHT_STRING; a LEVEL clause is not emulated.
     *
     * @throws \MySqlMemory\Error\SqlError When the call has a LEVEL clause
     */
    public function weight(WeightString $node, Scope $scope): Evaluable
    {
        if ($node->levels !== [] || $node->range !== null) {
            throw StatementError::NotSupportedYet->error('WEIGHT_STRING with LEVEL');
        }

        return new Weight($this->compiler->compile($node->subject, $scope), $node->cast, $node->length === null ? null : (int) $node->length->text, $this->compiler->domain($node));
    }

    /**
     * Compiles MATCH (columns) AGAINST (expr) as far as the server checks it before it searches.
     *
     * The text searched for is constant for the statement, else the call is an error naming
     * AGAINST; the columns are of one table, else it names MATCH; and a FULLTEXT index of the
     * table has exactly those columns, in any order, else no index matches. The search itself is
     * not emulated (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html.
     *
     * @throws \MySqlMemory\Error\SqlError When the call is refused, or when it would search
     */
    public function match(FullTextSearch $node, Scope $scope): Evaluable
    {
        if ($this->compiler->constancy($node->against) === Constancy::Row) {
            throw StatementError::WrongArguments->error('AGAINST');
        }
        $table = null;
        $positions = [];
        foreach ($node->columns as $column) {
            $resolution = $this->compiler->facts->scalar($column)->resolution;
            if (!$resolution instanceof ResolvedColumn) {
                throw StatementError::WrongArguments->error('MATCH');
            }
            $located = $scope->locate($resolution->relation);
            $definition = $located === null ? null : ($located[1]->tables[spl_object_id($resolution->relation)] ?? null);
            if ($located === null || $definition === null || ($table !== null && $table !== $definition)) {
                throw StatementError::WrongArguments->error('MATCH');
            }
            $table = $definition;
            $positions[] = $this->compiler->names->position($located[1], $resolution);
        }
        sort($positions);
        foreach ($table->keys as $key) {
            $indexed = $key->columns;
            sort($indexed);
            if ($key->kind === KeyKind::FullText && $indexed === $positions) {
                throw StatementError::NotSupportedYet->error('MATCH ... AGAINST');
            }
        }

        throw SchemaError::FullTextIndexNotFound->error();
    }
}
