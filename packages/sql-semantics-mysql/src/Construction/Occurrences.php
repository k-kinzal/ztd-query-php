<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Construction;

use Closure;
use ReflectionClass;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Source\SourceNotice;
use SqlSemantics\Statement\Source\Span;

/**
 * Constructs fresh occurrences before binding an existing semantic structure.
 *
 * Every copy goes through its concrete constructor. Declarations and other
 * non-occurrence values retain their identity. No SQL is rendered or parsed.
 *
 * @visibility public
 * @example Preparing another binding without SQL text
 *     $number = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('12');
 *     $copy = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($number);
 *     [$copy->text, $copy === $number, $copy->hasBinding()] // => ['12', false, false]
 */
final class Occurrences
{
    /**
     * Starts another binding of the same concrete SQL structure.
     *
     * @template T of Node
     * @param T $node The structure whose occurrences need fresh local information
     * @return T
     */
    public static function fresh(Node $node): Node
    {
        $copy = (new self())->copy($node);
        Check::invariant($copy instanceof $node, 'Fresh occurrences preserve the concrete SQL type.');
        return $copy;
    }

    /**
 * @var array<int, Node>
 */
    private array $copies = [];

    /**
     * @param Closure(Node): ?Node|null $replace Replacements of structural operands
     */
    public function __construct(private readonly ?Closure $replace = null, private readonly SourceMap $sources = new SourceMap())
    {
    }

    /**
     * Reconstructs an occurrence with fresh local binding slots.
     *
     */
    public function copy(Node $node): Node
    {
        return $this->node($node);
    }

    /**
     * Reconstructs an occurrence, applying explicit changes through its constructor.
     *
     * @param array<string, mixed> $changes Constructor operands to replace
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an occurrence does not expose its constructor operands
     */
    public function node(Node $node, array $changes = []): Node
    {
        $replacement = $this->replace === null ? null : ($this->replace)($node);
        if ($replacement !== null) {
            return (new self())->node($replacement, $changes);
        }
        $id = spl_object_id($node);
        if ($changes === [] && isset($this->copies[$id])) {
            return $this->copies[$id];
        }
        try {
            $class = new ReflectionClass($node);
            $arguments = [];
            $parameters = $class->getConstructor()?->getParameters() ?? [];
            Check::input(array_diff(array_keys($changes), array_map(static fn ($parameter): string => $parameter->getName(), $parameters)) === [], 'Changed operands name concrete constructor parameters.');
            foreach ($parameters as $parameter) {
                $name = $parameter->getName();
                if (array_key_exists($name, $changes)) {
                    $arguments[$name] = $this->value($changes[$name]);
                } elseif (in_array($name, ['scalarInformation', 'relationInformation', 'queryInformation', 'statementInformation', 'divisionPrecision'], true)) {
                    $arguments[$name] = null;
                } elseif ($name === 'inputSource' || $name === 'location') {
                    $origin = $this->sources->of($node);
                    $arguments[$name] = $origin === null ? $node->source() : new Span($origin->offset, $origin->length);
                } elseif ($class->hasProperty($name)) {
                    $arguments[$name] = $this->value($class->getProperty($name)->getValue($node));
                } else {
                    $arguments[$name] = $this->operand($node, $name);
                }
            }
            $copy = $class->newInstanceArgs($arguments);
            return $this->copies[$id] = $copy;
        } catch (ReflectionException $failure) {
            throw new \SqlSemantics\Diagnostic\InvalidConstruction('A SQL occurrence must expose its concrete constructor operands.', 0, $failure);
        }
    }

    /**
     * Replaces occurrence identities in nested operands, retaining declaration values.
     */
    public function value(mixed $value): mixed
    {
        if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput) {
            $tables = Check::listOf(array_map($this->copy(...), $value->tables), \SqlSemantics\Platform\MySql\Statement\Relation\TableSource::class, 'A replacement preserves DELETE table inputs.');
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput($tables, $value->targets, wildcards: $value->wildcards);
        }
        if ($value instanceof Node) {
            return $this->copy($value);
        }
        return is_array($value) ? array_map($this->value(...), $value) : $value;
    }

    /**
     * Reconstructs the few constructor operands stored in normalized form.
     */
    public function operand(Node $node, string $name): FunctionImplementation|GroupConcat|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate|DeferredJsonObjectAggregate|Aggregate
    {
        if ($node instanceof CreateFunction && $name === 'implementation') {
            $body = $this->copy($node->body);
            Check::input($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody || $body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement || $body instanceof \SqlSemantics\Platform\MySql\Statement\Statement, 'A replacement preserves a function implementation body.');
            $characteristics = Check::listOf(array_map($this->copy(...), $node->characteristics), \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic::class, 'A replacement preserves routine characteristics.');
            return new FunctionImplementation($body, $characteristics);
        }
        Check::invariant($node instanceof DeferredWindowAggregate && $name === 'aggregate', 'A concrete constructor operand must be retained: ' . $node::class . '::$' . $name);
        $arguments = Check::listOf(array_map($this->copy(...), $node->arguments), \SqlSemantics\Statement\Scalar::class, 'An aggregate replacement preserves scalar arguments.');
        if ($node->function === DeferredAggregateKind::GroupConcat) {
            $order = Check::listOf(array_map($this->copy(...), $node->order), \SqlSemantics\Platform\MySql\Statement\Query\OrderItem::class, 'An aggregate replacement preserves ordering operands.');
            return new GroupConcat($arguments, $node->distinct, $order, $node->separator);
        }
        if ($node->function === DeferredAggregateKind::JsonObject) {
            return (new \SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting())->problem($arguments, false) === null
                ? new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate($arguments[0], $arguments[1], $node->all, $node->valueAll)
                : new DeferredJsonObjectAggregate($arguments[0], $arguments[1], $node->all, $node->valueAll);
        }
        return new Aggregate($node->function, $arguments, $node->distinct, $node->all, alias: $node->alias);
    }

    /**
     * Moves input locations to the reconstructed occurrences.
     */
    public function sources(): SourceMap
    {
        $origins = [];
        foreach ($this->sources->origins as $origin) {
            Check::invariant(isset($this->copies[spl_object_id($origin->node)]), 'A source range belongs to a copied occurrence.');
            $origins[] = new Origin($this->copies[spl_object_id($origin->node)], $origin->offset, $origin->length);
        }
        $notices = [];
        foreach ($this->sources->notices as $notice) {
            $subject = $notice->subject;
            $notices[] = new SourceNotice($subject instanceof Node ? $this->copies[spl_object_id($subject)] : $subject, $notice->warning, $notice->offset);
        }
        return new SourceMap($origins, $notices);
    }
}
