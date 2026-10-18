<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Enumeration\Finite;

use Closure;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Evidence\Provenance;
use Deriver\Deriver\Evaluator\Language\DeclarationCoercion;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;

/**
 * A closed observation domain and the outcomes still needed by its consumer.
 * Membership is checked after declaration conversions without replacing raw operands.
 * @visibility root
 */
final class Demand
{
    /**
     * Stable cache identity for this observation and its missing members.
     */
    public readonly string $identity;

    /**
     * @param array<string, true> $remaining Outcomes without witnesses
     * @param Closure(Term): Term|null $convert Conversions from this input to the observation
     */
    public function __construct(public readonly array $remaining, private readonly string $projection, private readonly bool $truth = false, private readonly ?Closure $convert = null)
    {
        $this->identity = hash('sha256', $projection . ':' . serialize(array_keys($remaining)));
    }

    /**
     * Defines PHP boolean conversion as a finite observation.
     */
    public static function truth(int $wanted = 3): self
    {
        $remaining = [];
        if (($wanted & 1) !== 0) {
            $remaining['b:0'] = true;
        }
        if (($wanted & 2) !== 0) {
            $remaining['b:1'] = true;
        }
        return new self($remaining, 'truth:' . $wanted, true);
    }

    /**
     * Adds one declaration boundary before the existing outer conversions.
     */
    public function through(Evaluator $engine, string $type, bool $strict, string $class = ''): self
    {
        if (in_array($type, ['', 'mixed', 'void'], true)) {
            return $this;
        }
        return new self($this->remaining, hash('sha256', $this->projection . ':type:' . $type . ':' . (int) $strict . ':' . $class), $this->truth, function (Term $value) use ($engine, $type, $strict, $class): Term {
            $value = (new DeclarationCoercion())->check($engine, $value, $type, $strict, $class);
            return $this->convert === null ? $value : ($this->convert)($value);
        });
    }

    /**
     * Stops opening dependencies once every missing outcome has a witness.
     * @param iterable<int, Closure(int, self): Term> $branches Lazy alternatives
     */
    public function collect(iterable $branches, Provenance $provenance): Term
    {
        $missing = $this->remaining;
        $known = [];
        $unknown = [];
        $failures = [];
        $choices = new Choices($provenance);
        foreach ($branches as $branch) {
            $request = new self($missing, $this->projection, $this->truth, $this->convert);
            foreach ($choices->alternatives($branch(3, $request)) as [$value, $guard]) {
                $key = $this->key($value);
                if ($key === null) {
                    $observed = $this->convert === null ? $value : ($this->convert)($value);
                    if ($observed->kind === TermKind::Throwable || $observed->kind === TermKind::NoValue) {
                        $failures[] = [$value, $guard];
                    } else {
                        $unknown[] = [$value, $guard];
                    }
                } else {
                    $known[$key] ??= [$value, $guard];
                    unset($missing[$key]);
                }
                if ($missing === []) {
                    return $choices->make([...array_values($known), ...$failures]);
                }
            }
        }
        return $choices->make([...array_values($known), ...$unknown, ...$failures]);
    }

    /**
     * Identifies witnessed observations without treating a type bound as a value.
     */
    public function key(Term $value): ?string
    {
        $value = $this->convert === null ? $value : ($this->convert)($value);
        if ($this->truth) {
            $truth = (new Operations())->truth($value);
            return $truth === null ? null : 'b:' . (int) $truth;
        }
        return self::identity($value);
    }

    /**
     * Gives exact scalar and enum cases stable, disjoint identities.
     */
    public static function identity(Term $value): ?string
    {
        if ($value->kind === TermKind::Enum) {
            return 'enum:' . $value->literal;
        }
        if ($value->kind !== TermKind::Constant) {
            return null;
        }
        return is_bool($value->literal) ? 'b:' . (int) $value->literal : 'scalar:' . serialize($value->literal);
    }
}
