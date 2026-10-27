<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use ReflectionClass;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Source\SourceNotice;
use SqlSemantics\Statement\Source\Span;
use UnitEnum;

/**
 * Constructs fresh SQL occurrences before resolving another declaration context.
 * Constructors recheck every operand; declaration objects retain their identity.
 *
 * @visibility public
 * @example Reusing SQL operands with fresh occurrence bindings
 *     $query = new \SqlSemantics\Statement\Script([]);
 *     $copy = (new \SqlSemantics\Construction\Occurrences())->copy($query);
 *     [$copy === $query, $copy->hasBinding(), $query->hasBinding()] // => [false, false, false]
 */
final class Occurrences
{
    /**
 * @var array<int, Node>
 */
    private array $copies = [];

    /**
 * Accepts input ranges only for the duration of construction.
 */
    public function __construct(private readonly SourceMap $sources = new SourceMap())
    {
    }

    /**
     * Reconstructs the concrete occurrence with fresh binding slots.
     *
     * @template T of Node
     * @param T $node The SQL occurrence to copy
     * @return T
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an occurrence does not expose its constructor operands
     */
    public function copy(Node $node): Node
    {
        if ($node instanceof UnitEnum) {
            return $node;
        }
        $id = spl_object_id($node);
        if (isset($this->copies[$id])) {
            $copy = $this->copies[$id];
            Check::invariant($copy instanceof $node, 'An occurrence copy preserves its concrete type.');
            return $copy;
        }
        try {
            $class = new ReflectionClass($node);
            $arguments = [];
            foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                $name = $parameter->getName();
                if (in_array($name, ['scalarInformation', 'relationInformation', 'queryInformation', 'statementInformation'], true)) {
                    $arguments[$name] = null;
                } elseif ($name === 'inputSource') {
                    $origin = $this->sources->of($node);
                    $arguments[$name] = $origin === null ? $node->source() : new Span($origin->offset, $origin->length);
                } else {
                    $arguments[$name] = $this->value($class->getProperty($name)->getValue($node));
                }
            }
            $copy = $class->newInstanceArgs($arguments);
            $this->copies[$id] = $copy;
            return $copy;
        } catch (ReflectionException $failure) {
            throw new \SqlSemantics\Diagnostic\InvalidConstruction('A SQL occurrence must expose its concrete constructor operands.', 0, $failure);
        }
    }

    /**
 * Reconstructs nested occurrences while retaining non-occurrence values.
 */
    public function value(mixed $value): mixed
    {
        if ($value instanceof Node) {
            return $this->copy($value);
        }
        return is_array($value) ? array_map($this->value(...), $value) : $value;
    }

    /**
 * Moves temporary source records to the new occurrence identities.
 */
    public function sources(): SourceMap
    {
        $origins = [];
        foreach ($this->sources->origins as $origin) {
            $copy = $this->copies[spl_object_id($origin->node)] ?? null;
            Check::invariant($copy !== null, 'An input range belongs to a copied occurrence.');
            $origins[] = new Origin($copy, $origin->offset, $origin->length);
        }
        $notices = [];
        foreach ($this->sources->notices as $notice) {
            $subject = $notice->subject instanceof Node ? $this->copies[spl_object_id($notice->subject)] : $notice->subject;
            $notices[] = new SourceNotice($subject, $notice->warning, $notice->offset);
        }
        return new SourceMap($origins, $notices);
    }
}
