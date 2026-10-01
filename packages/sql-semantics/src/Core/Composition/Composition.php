<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Composition;

use SqlSemantics\Core\Analysis\LeafReader;
use SqlSemantics\Core\Analysis\Vocabulary;
use SqlSemantics\Core\Builder;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Writer;

/**
 * The composition every database builder shares: forms by their symbols, leaves by their spelling, and operands by their binding.
 *
 * A database package supplies the spelling rules of its names, the roles
 * its values occupy, and the contracts of its expression forms. Everything
 * else is read from the vocabulary of the release, so a builder never names
 * a generated class.
 *
 * @visibility SqlSemantics
 */
abstract class Composition implements Builder
{
    use Templating;

    protected readonly LeafReader $leaves;
    protected readonly Vocabulary $vocabulary;

    /**
     * Composes values of the language.
     */
    public function __construct(protected readonly Language $language)
    {
        $this->leaves = new LeafReader($language);
        $this->vocabulary = $language->vocabulary();
    }

    /**
     * Answers the binding contracts of the expression forms of this database.
     */
    abstract protected function operands(): Operands;

    /**
     * Answers the namespace of the generated model of this database, below `SqlSemantics\Statement\Model`.
     */
    abstract protected function modelNamespace(): string;

    /**
     * Spells a name in the identifier quotes of this database.
     */
    abstract protected function quote(string $name): string;

    /**
     * Answers the bare spelling of a name when its characters and case survive unquoted, or null.
     */
    abstract protected function bare(string $name): ?string;

    /**
     * Answers the terminal names of unquoted and quoted identifiers.
     *
     * @return list<string>
     */
    abstract protected function identifierTerminals(): array;

    /**
     * Answers whether a keyword the grammar admits in a name role may be written bare.
     */
    abstract protected function admitsBareKeyword(): bool;

    /**
     * Lists the forms that wrap one statement as their first child, such as a statement with its terminator.
     *
     * @return list<array{string, list<string>}> Rules and their symbols
     */
    abstract protected function envelopes(): array;

    /**
     * Answers the statement a root envelope holds, when its other children write nothing; otherwise the value itself.
     */
    protected function unwrap(Element $value): Element
    {
        foreach ($this->envelopes() as [$rule, $symbols]) {
            $class = $this->classOf($rule, $symbols);
            if ($class === null || !$value instanceof $class) {
                continue;
            }
            $children = $value->children();
            foreach (array_slice($children, 1) as $other) {
                if (Writer::render($other) !== '') {
                    continue 2;
                }
            }

            return $this->unwrap($children[0]);
        }

        return $value;
    }

    /**
     * Builds the form of a rule written with the symbols, from its field arguments.
     *
     * @param list<string> $symbols
     * @param list<Element|string> $arguments
     *
     * @throws CompositionException When the release has no such form
     */
    protected function form(string $rule, array $symbols, array $arguments): Element
    {
        $recipe = $this->vocabulary->form($rule, $symbols);
        if ($recipe === null) {
            throw new CompositionException('No form ' . $rule . ' [' . implode(' ', $symbols) . '] in ' . $this->language->version);
        }

        return $this->vocabulary->build($recipe, $arguments);
    }

    /**
     * Answers whether the release has the form of a rule written with the symbols.
     *
     * @param list<string> $symbols
     */
    protected function has(string $rule, array $symbols): bool
    {
        return $this->vocabulary->form($rule, $symbols) !== null;
    }

    /**
     * Answers the value class of a form, so a value can be recognized as that form.
     *
     * @param list<string> $symbols
     * @return class-string<Element>|null
     */
    protected function classOf(string $rule, array $symbols): ?string
    {
        $recipe = $this->vocabulary->form($rule, $symbols);

        return $recipe['class'] ?? null;
    }

    /**
     * Reads the leaf a role admits for a spelling.
     *
     * @throws CompositionException When the role admits no such leaf
     */
    protected function leaf(string $role, string $text): Element
    {
        $leaf = $this->leaves->read($role, $text);
        if ($leaf === null) {
            throw new CompositionException('The role ' . $role . ' admits no ' . $text . ' in ' . $this->language->version);
        }

        return $leaf;
    }

    /**
     * Spells a name in a role: bare when the release reads it as that name, otherwise quoted.
     *
     * @param string $prefix The text the lexer has just read before the name, such as the `.` of a qualified name
     *
     * @throws CompositionException When the name is empty or the role admits no quoted name
     */
    protected function name(string $role, string $name, string $prefix = ''): Element
    {
        if ($name === '') {
            throw new CompositionException('A name must not be empty.');
        }
        $bare = $this->bare($name);
        if ($bare !== null) {
            $terminal = $this->leaves->terminal($bare, $prefix);
            if ($terminal !== null && ($this->admitsBareKeyword() || in_array($terminal, $this->identifierTerminals(), true))) {
                $leaf = $this->leaves->read($role, $bare, $prefix);
                if ($leaf !== null) {
                    return $leaf;
                }
            }
        }
        $leaf = $this->leaves->read($role, $this->quote($name), $prefix);
        if ($leaf === null) {
            throw new CompositionException('The role ' . $role . ' admits no quoted name in ' . $this->language->version);
        }

        return $leaf;
    }

    /**
     * Places an operand at a symbol position of an expression form, parenthesized when it does not belong there.
     *
     * An operand belongs at a position when it is a value of the position's
     * role and binds at least as strongly as the form requires.
     *
     * @param list<string> $symbols
     */
    protected function operand(Element $value, string $rule, array $symbols, int $position): Element
    {
        $class = $this->classOf($rule, $symbols);
        $role = $this->roleInterface($symbols[$position]);
        if ($class !== null && $value instanceof $role && $this->operands()->fits($value, $class, $position, $symbols[$position])) {
            return $value;
        }

        return $this->parenthesized($value);
    }

    /**
     * Requires a value to occupy a role.
     *
     * @throws CompositionException When it does not
     */
    protected function expect(Element $value, string $symbol, string $what): Element
    {
        $role = $this->roleInterface($symbol);
        if (!$value instanceof $role) {
            throw new CompositionException($what . ' must be a ' . $symbol . ' of ' . $this->language->version . ', ' . $value::class . ' given.');
        }

        return $value;
    }

    /**
     * Folds values into a left-recursive list form, the first value standing alone.
     *
     * @param list<string> $symbols The symbols of the recursive form, the list itself first
     * @param list<Element> $values
     *
     * @throws CompositionException When there is no value to fold
     */
    protected function fold(string $rule, array $symbols, array $values, int $listPosition = 0, int $itemPosition = 2): Element
    {
        $list = array_shift($values);
        if ($list === null) {
            throw new CompositionException('A ' . $rule . ' needs at least one value.');
        }
        foreach ($values as $value) {
            $arguments = [];
            foreach ($symbols as $index => $symbol) {
                if ($index === $listPosition) {
                    $arguments[] = $list;
                } elseif ($index === $itemPosition) {
                    $arguments[] = $value;
                }
            }
            $list = $this->form($rule, $symbols, $arguments);
        }

        return $list;
    }

    /**
     * Unfolds a left-recursive list form into its values: the list is its first child and the item its last.
     *
     * @param list<string> $symbols The symbols of the recursive form
     * @return list<Element>
     */
    protected function unfold(Element $list, string $rule, array $symbols): array
    {
        $class = $this->classOf($rule, $symbols);
        if ($class === null || !$list instanceof $class) {
            return [$list];
        }
        $children = $list->children();

        return [...$this->unfold($children[0], $rule, $symbols), $children[count($children) - 1]];
    }

    /**
     * Names the role interface of a grammar symbol.
     *
     * @return class-string<Element>
     */
    protected function roleInterface(string $symbol): string
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $symbol) ?? $symbol;
        $name = preg_replace('/([A-Z])([A-Z][a-z])/', '$1 $2', $name) ?? $name;
        $name = str_replace(' ', '', ucwords(strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $name) ?? '')));
        /** @var class-string<Element> $role */
        $role = $this->modelNamespace() . '\\Role\\' . $name . 'Form';

        return $role;
    }

    /**
     * Spells a string literal in single quotes, doubling quotes and optionally backslashes.
     */
    protected function quotedString(string $value, bool $backslashEscapes): string
    {
        $escaped = str_replace("'", "''", $backslashEscapes ? str_replace('\\', '\\\\', $value) : $value);
        if ($backslashEscapes) {
            $escaped = str_replace("\0", '\\0', $escaped);
        }

        return "'" . $escaped . "'";
    }

    /**
     * Spells the magnitude of a finite number so the language reads it as a non-integer that is exactly the number.
     *
     * The digits are the fewest that read back as the same double, found
     * without the `precision` or `serialize_precision` setting, so the
     * spelling is the same in every environment. The number is written
     * positionally when that is short, and with an exponent otherwise or
     * when the language needs the exponent to read an approximate number.
     *
     * @param bool $exponent Whether to always write the exponent
     *
     * @throws CompositionException When the value is not finite
     */
    protected function decimal(float $value, bool $exponent = false): string
    {
        if (!is_finite($value)) {
            throw new CompositionException('A numeric literal must be finite.');
        }
        [$digits, $power] = $this->digits(abs($value));
        if ($exponent || $power < -7 || $power > 20) {
            return $digits[0] . (strlen($digits) > 1 ? '.' . substr($digits, 1) : '') . 'e' . $power;
        }
        if ($power < 0) {
            return '0.' . str_repeat('0', -$power - 1) . $digits;
        }
        $digits = str_pad($digits, $power + 1, '0');

        return substr($digits, 0, $power + 1) . '.' . (strlen($digits) > $power + 1 ? substr($digits, $power + 1) : '0');
    }

    /**
     * Answers the fewest significant digits that read back as exactly a finite, non-negative double, and the power of ten of the first.
     *
     * @return array{non-empty-string, int}
     */
    protected function digits(float $magnitude): array
    {
        $precision = 0;
        do {
            [$mantissa, $power] = explode('e', sprintf('%.' . $precision . 'e', $magnitude)) + ['', '0'];
            $digits = preg_replace('/\D/', '', $mantissa) ?? '';
        } while ((float) (substr($digits, 0, 1) . '.' . substr($digits, 1) . 'e' . $power) !== $magnitude && ++$precision < 17);
        $digits = rtrim($digits, '0');

        return [$digits === '' ? '0' : $digits, (int) $power];
    }

    /**
     * Reports whether a number is negative, negative zero included.
     */
    protected function negative(float $value): bool
    {
        return $value < 0 || ($value === 0.0 && fdiv(1.0, $value) < 0);
    }

    /**
     * Spells a parameter marker: the language's own for a position, or `:name` for a name the language reads.
     *
     * @param string $positional The language's marker for the position, when the marker is a position
     *
     * @throws CompositionException When the position is below one, the name is not a name, or the language reads no named placeholder
     */
    protected function marker(int|string $marker, string $positional): string
    {
        if (is_int($marker)) {
            if ($marker < 1) {
                throw new CompositionException('A parameter position counts from one.');
            }

            return $positional;
        }
        if (preg_match('/^[A-Za-z0-9_]+$/D', $marker) !== 1) {
            throw new CompositionException('A parameter name holds letters, digits, and underscores.');
        }
        if ($this->leaves->terminal(':' . $marker) === null) {
            throw new CompositionException('The language reads no named placeholder; select the named parameter syntax.');
        }

        return ':' . $marker;
    }

    /**
     * Spells bytes as a hexadecimal string literal body.
     */
    protected function hex(string $bytes): string
    {
        return "X'" . bin2hex($bytes) . "'";
    }
}
