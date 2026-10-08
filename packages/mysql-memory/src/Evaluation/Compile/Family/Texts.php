<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use Closure;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Operator\Weight;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
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
            throw ErrorCode::UnknownCollation->error($node->collation->value);
        }
        $domain = $operand->domain();
        $charset = $domain->kind === Kind::String ? $domain->collation->charset : $this->compiler->settings->connectionCollation->charset;
        if ($collation->charset !== $charset) {
            throw ErrorCode::CollationCharsetMismatch->error($collation->name, $charset->name);
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
            throw ErrorCode::UnknownCharacterSet->error($name);
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

        return $this->call('CHAR', $arguments, $result, static function (Frame $f, array $a): string {
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

            return $bytes;
        });
    }

    /**
     * Compiles `a SOUNDS LIKE b`: SOUNDEX(a) = SOUNDEX(b).
     */
    public function soundsLike(SoundsLike $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->operand, $scope);
        $right = $this->compiler->compile($node->pattern, $scope);
        $domain = $this->compiler->domain($node);

        return $this->call('SOUNDS LIKE', [$left, $right], $domain, static function (Frame $f, array $a): ?int {
            $one = Convert::toText($a[0]->evaluate($f), $a[0]->domain());
            $two = Convert::toText($a[1]->evaluate($f), $a[1]->domain());

            return $one === null || $two === null ? null : (Texts::soundex($one) === Texts::soundex($two) ? 1 : 0);
        });
    }

    /**
     * Answers the SOUNDEX code of a string: its first letter and the digits of the consonants after it.
     */
    public static function soundex(string $text): string
    {
        $codes = ['B' => 1, 'F' => 1, 'P' => 1, 'V' => 1, 'C' => 2, 'G' => 2, 'J' => 2, 'K' => 2, 'Q' => 2, 'S' => 2, 'X' => 2, 'Z' => 2, 'D' => 3, 'T' => 3, 'L' => 4, 'M' => 5, 'N' => 5, 'R' => 6];
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($text)) ?? '';
        if ($letters === '') {
            return '';
        }
        $result = $letters[0];
        $last = $codes[$letters[0]] ?? 0;
        for ($i = 1, $length = strlen($letters); $i < $length; $i++) {
            $code = $codes[$letters[$i]] ?? 0;
            if ($code !== 0 && $code !== $last) {
                $result .= $code;
            }
            if ($letters[$i] !== 'H' && $letters[$i] !== 'W') {
                $last = $code;
            }
        }

        return str_pad($result, 4, '0');
    }

    /**
     * Compiles `a [NOT] REGEXP b`: whether a matches the regular expression b, in the collation of both.
     *
     * A binary string matched with a string of another character set, either way, is an error
     * (ER_CHARACTER_SET_MISMATCH) that names the binary side 'binary' and the other by its
     * collation; a value that is no string mixes with either (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the collations do not mix
     */
    public function regexp(Regexp $node, Scope $scope): Evaluable
    {
        $subject = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $sides = array_map(static fn (Domain $domain): ?string => $domain->kind !== Kind::String ? null : ($domain->collation->charset === Charset::binary() ? 'binary' : $domain->collation->name), [$subject->domain(), $pattern->domain()]);
        if ($sides[0] !== null && $sides[1] !== null && ($sides[0] === 'binary') !== ($sides[1] === 'binary')) {
            throw ErrorCode::CharacterSetMismatch->error($sides[0], $sides[1], 'regexp_like');
        }
        [$collation] = Collations::aggregate([$subject->domain(), $pattern->domain()], 'regexp_like', $this->compiler->settings->connectionCollation, true);
        $negated = $node->negated;
        $domain = $this->compiler->domain($node);

        return $this->call('REGEXP', [$subject, $pattern], $domain, static function (Frame $f, array $a) use ($collation, $negated): ?int {
            $text = Convert::toText($a[0]->evaluate($f), $a[0]->domain());
            $expression = Convert::toText($a[1]->evaluate($f), $a[1]->domain());
            if ($text === null || $expression === null) {
                return null;
            }
            $strings = new Strings();
            $text = Encoding::convert($text, $strings->charset($a[0]->domain()), Charset::known('utf8mb4'));
            $expression = Encoding::convert($expression, $strings->charset($a[1]->domain()), Charset::known('utf8mb4'));
            $flags = $collation->binaryOrder() || str_ends_with($collation->name, '_cs') ? 'u' : 'ui';
            $matched = @preg_match('/' . str_replace('/', '\\/', $expression) . '/' . $flags, $text);
            if ($matched === false) {
                throw ErrorCode::RegexpError->error('The regular expression is not valid.');
            }

            return ($matched === 1) !== $negated ? 1 : 0;
        });
    }

    /**
     * Compiles WEIGHT_STRING; a LEVEL clause is not emulated.
     *
     * @throws \MySqlMemory\Error\SqlError When the call has a LEVEL clause
     */
    public function weight(WeightString $node, Scope $scope): Evaluable
    {
        if ($node->levels !== [] || $node->range !== null) {
            throw ErrorCode::NotSupportedYet->error('WEIGHT_STRING with LEVEL');
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
            throw ErrorCode::WrongArguments->error('AGAINST');
        }
        $table = null;
        $positions = [];
        foreach ($node->columns as $column) {
            $resolution = $this->compiler->facts->scalar($column)->resolution;
            if (!$resolution instanceof ResolvedColumn) {
                throw ErrorCode::WrongArguments->error('MATCH');
            }
            $located = $scope->locate($resolution->relation);
            $definition = $located === null ? null : ($located[1]->tables[spl_object_id($resolution->relation)] ?? null);
            if ($located === null || $definition === null || ($table !== null && $table !== $definition)) {
                throw ErrorCode::WrongArguments->error('MATCH');
            }
            $table = $definition;
            $positions[] = $this->compiler->names->position($located[1], $resolution);
        }
        sort($positions);
        foreach ($table->keys as $key) {
            $indexed = $key->columns;
            sort($indexed);
            if ($key->kind === KeyKind::FullText && $indexed === $positions) {
                throw ErrorCode::NotSupportedYet->error('MATCH ... AGAINST');
            }
        }

        throw ErrorCode::FullTextIndexNotFound->error();
    }
}
