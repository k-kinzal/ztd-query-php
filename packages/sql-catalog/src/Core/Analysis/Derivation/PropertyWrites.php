<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Php\ProgramIndex;

/**
 * What a property of an object can hold when a method starts, read from everywhere the class writes it.
 *
 * A method that reads `$this->table` without writing it first sees whatever
 * the object was left holding: the declared default, or the value some
 * method of the class assigned — most often the constructor, from an
 * argument the code that creates the object passes. Each of those writes is
 * worked out at the place it is written, like any other value, so a table
 * name handed to a repository's constructor resolves where the repository
 * issues its queries.
 *
 * @visibility root
 */
final class PropertyWrites
{
    private ProgramIndex $index;

    private NodeFinder $finder;

    private ModifiedNames $modified;

    /**
     * @var array<string, list<Stmt\ClassMethod>>
     */
    private array $writers = [];

    /**
     * @var array<string, true>
     */
    private array $reading = [];

    /**
     * Wires the reader to the declarations it looks for writes in.
     */
    public function __construct(ProgramIndex $index, ?ModifiedNames $modified = null)
    {
        $this->index = $index;
        $this->finder = new NodeFinder();
        $this->modified = $modified ?? new ModifiedNames();
    }

    /**
     * Every value the property can hold when a method of the class starts.
     *
     * Each method that writes the property is read at the point it finishes,
     * so a property built up in steps — assigned, then appended to under a
     * condition — is seen as each of the values it can be left holding rather
     * than as whichever assignment happens to come last in the source.
     *
     * @return list<Domain>
     */
    public function valuesOf(string $className, string $property, int $depth, Deriver $deriver): array
    {
        $key = strtolower($className) . '->' . $property;
        if (isset($this->reading[$key])) {
            return [];
        }
        $this->reading[$key] = true;
        $values = [];
        $goal = new Expr\PropertyFetch(new Expr\Variable(FreeNames::THIS), $property);
        foreach ($this->writersOf($className, $property) as $method) {
            if (!$deriver->spent()) {
                foreach ($deriver->solveAtExits($method, [$goal], $depth + 1) as $solution) {
                    $values[] = $solution->values[0];
                }
            }
        }
        foreach ($this->promotions($className, $property) as $constructor) {
            $values = array_merge($values, $deriver->binder()->parameterValues($constructor, $property, $depth + 1, $deriver));
        }
        $default = $this->defaultOf($className, $property);
        if ($default !== null) {
            $values[] = $deriver->binder()->evaluateConstant($default, $className);
        }
        unset($this->reading[$key]);

        return $values;
    }

    /**
     * The methods of the class and the classes it inherits from that write the property.
     *
     * @return list<Stmt\ClassMethod>
     */
    public function writersOf(string $className, string $property): array
    {
        $key = strtolower($className) . '->' . $property;
        if (isset($this->writers[$key])) {
            return $this->writers[$key];
        }
        $found = [];
        foreach ($this->index->lineage($className) as $shape) {
            foreach ($shape->methods as $method) {
                $node = $method->node;
                if ($node instanceof Stmt\ClassMethod && $this->writes($node, $property)) {
                    $found[] = $node;
                }
            }
        }
        $this->writers[$key] = $found;

        return $found;
    }

    /**
     * Whether a method writes the property of `$this` anywhere in its body.
     */
    public function writes(Stmt\ClassMethod $method, string $property): bool
    {
        $name = FreeNames::THIS . '->' . $property;
        foreach ($this->finder->find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) as $assignment) {
            if ($assignment instanceof Expr\Assign || $assignment instanceof Expr\AssignOp || $assignment instanceof Expr\AssignRef) {
                if ($this->modified->baseName($assignment->var) === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The constructors that promote a parameter of that name to the property.
     *
     * @return list<Stmt\ClassMethod>
     */
    public function promotions(string $className, string $property): array
    {
        $constructors = [];
        foreach ($this->index->lineage($className) as $shape) {
            $constructor = $shape->methods['__construct'] ?? null;
            $node = $constructor?->node;
            if (!$node instanceof Stmt\ClassMethod) {
                continue;
            }
            foreach ($node->params as $parameter) {
                if ($parameter->flags !== 0 && $parameter->var instanceof Expr\Variable && $parameter->var->name === $property) {
                    $constructors[] = $node;
                }
            }
        }

        return $constructors;
    }

    /**
     * The default the property is declared with, or null when it is declared without one.
     */
    public function defaultOf(string $className, string $property): ?Expr
    {
        foreach ($this->index->lineage($className) as $shape) {
            if (isset($shape->propertyDefaults[$property])) {
                return $shape->propertyDefaults[$property];
            }
        }

        return null;
    }
}
