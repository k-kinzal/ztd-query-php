# Relational fixture plans

A fixture plan describes the rows to generate and the columns that connect them.
The schema supplies column types; the plan supplies relationships. SQL Fixture
generates data in memory and does not insert rows into the database.

Register the schemas with `FixtureProvider`, or use the schema sources provided by
`DatabaseFixtureProvider` and `FileFixtureProvider`. All three providers accept a
`FixturePlan` or its textual representation through `fixtures()`.

## Ordinary relationships

```php
use SqlFixture\Plan\FixturePlan;

$plan = FixturePlan::table('orders')
    ->withOneToMany('orders.id', 'order_items.order_id')
    ->withManyToOne('orders.customer_id', 'customers.id');

$fixtures = $provider->fixtures($plan, [
    'orders' => ['id' => 100],
    'order_items' => 2,
]);

$order = $fixtures->row('orders');
$items = $fixtures->rows('order_items');
```

The equivalent relation string is:

```text
orders.id < order_items.order_id,
orders.customer_id > customers.id
```

The first table is the subject and defaults to one row. A child collection gets
its requested count, or a generated count when unspecified. Walking to a parent
generates one parent for each referencing row. Parent keys are copied into child
columns; auto-increment keys needed by a relation are assigned in memory.

| Syntax | Meaning |
| --- | --- |
| `orders.id < order_items.order_id` | One-to-many |
| `orders.customer_id > customers.id` | Many-to-one |
| `orders.id - shipping.order_id` | One-to-one; the right side holds the reference |
| `orders.id <? order_items.order_id` | The child collection may be empty |
| `orders.customer_id >? customers.id` | The parent is optional |
| `orders.(tenant, id) < order_items.(tenant, order_id)` | Positional composite key mapping |
| `orders.id < [order_items.order_id, shipments.order_id]` | Two ordinary relationships |
| `users.id < memberships.user_id, memberships.group_id > groups.id` | Many-to-many through an explicit junction table |

An override can be a count (`3`), shared column values (`['status' => 'paid']`),
a list of per-row values (`[['status' => 'paid'], ['status' => 'pending']]`), or
an empty list (`[]`) requesting no rows. Child counts apply to each parent.
Use `rows('table')` to read a table as a list regardless of how it was reached.

## Choose a parent using a discriminator

A `RelationChoice` chooses one branch for each row. Branch values are compared
strictly: the integer `1`, the string `'1'`, `true`, and `null` are distinct.

```php
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;

$plan = FixturePlan::table('comments')->withChoice(
    RelationChoice::on('comments.target_type')
        ->when('post', Relation::manyToOne('comments.target_id', 'posts.id'))
        ->when('video', Relation::manyToOne('comments.target_id', 'videos.id'))
);

$fixtures = $provider->fixtures($plan, [
    'comments' => [
        ['target_type' => 'post'],
        ['target_type' => 'video'],
        ['target_type' => 'post'],
    ],
]);
```

This produces two posts and one video, with each comment referencing the correct
parent. The same plan can be written as:

```text
choice comments.target_type {
    'post'  { comments.target_id > posts.id }
    'video' { comments.target_id > videos.id }
}
```

`choice` is a SQL Fixture extension to the existing DBML-style relation notation.
`FixturePlan::from($plan->toString())` retains its choice definitions. Literals
are single-quoted strings, integers, `true`, `false`, or `null`. Escape an
apostrophe by doubling it: `'editor''s post'`. Separators inside literals are
preserved. Separate relations inside a branch with commas, semicolons, or newlines.

The builders return new values, so a plan can be reused across generation calls.
Supplying the same Faker seed, plan and overrides reproduces branch selection.
When the discriminator is omitted, a value is selected from the explicit cases.
The fallback is never selected randomly. A supplied value without a matching
case or fallback raises `ChoiceValueException`.

## Choose required child tables

Branches can use any ordinary relationship operator. They may contain multiple
relations or none at all.

```text
choice payments.method {
    'card' {
        payments.id - card_details.payment_id
        payments.id < receipts.payment_id
    }
    'bank' { payments.id - bank_details.payment_id }
    'free' {}
}
```

For each card payment, this generates its card details and receipts. Each bank
payment gets bank details. Free payments need neither. A table used only by an
inactive branch receives no rows, even when overrides are provided for it.
A table required by another active relationship can still be generated.
All candidate tables remain in the `FixtureSet`; `rows()` returns `[]` for an
unused table. All candidate schemas must be available and every referenced
column is validated before generation, including inactive branches.

## Optional relationships, fallback and separate foreign keys

```php
$choice = RelationChoice::on('events.target_type')
    ->when('post', Relation::manyToOne('events.post_id', 'posts.id'))
    ->when('video', Relation::manyToOne('events.video_id', 'videos.id'))
    ->when(null)
    ->otherwise();
```

An empty branch means no relationship. Foreign keys managed exclusively by
inactive branches are set to `null`. In the example, a post event gets a
`post_id` and a null `video_id`; a null or unmatched discriminator leaves both
null. These columns must permit NULL in the schema. An explicit non-null value
for an inactive key raises `ChoiceValueException`.

An optional parent within a selected branch is generated when overrides request
that parent. Otherwise its unspecified reference columns are set to NULL. This
choice-specific behavior prevents random dangling references.

## Composite keys and explicit references

```text
choice comments.target_type {
    'post'  { comments.(tenant_id, target_id) > posts.(tenant_id, id) }
    'video' { comments.(tenant_id, target_id) > videos.(tenant_id, id) }
}
```

A partially supplied composite reference is carried into the generated parent.
For example, overriding `comments.tenant_id` fixes the generated parent's
`tenant_id` as well. Conflicting parent or child overrides raise
`RelationValueException`; an inherited key is never silently overwritten.

When every referencing column is supplied explicitly, existing behavior is
preserved: no parent is generated. This permits references to rows supplied
outside the fixture plan. SQL Fixture does not check that an external row exists.

## Inverse generation and junction tables

Start from a candidate parent to generate matching children:

```text
posts,
choice comments.target_type {
    'post'  { comments.target_id > posts.id }
    'video' { comments.target_id > videos.id }
}
```

Generated comments receive `target_type = 'post'`. Explicitly requesting a
conflicting discriminator fails. Equivalent inverse edges shared by multiple
cases are traversed once; the discriminator is still chosen separately for
each child. Reaching a fallback-only edge requires an explicit discriminator.

Combine a choice with an ordinary junction relationship for polymorphic
many-to-many fixtures:

```text
tags.id < taggings.tag_id,
choice taggings.target_type {
    'post'  { taggings.target_id > posts.id }
    'video' { taggings.target_id > videos.id }
}
```

## Scope and other relationship patterns

Choices use equality against one discriminator column. Each conditional relation
must touch the discriminator table. Declare further dependencies as ordinary
relations outside the choice. Independent choices can coexist when their
foreign-key bindings do not overlap. A foreign key may have alternative bindings
inside one choice, but simultaneous conflicting bindings are rejected.

| Pattern | Representation or remaining design work |
| --- | --- |
| Polymorphic parents | A choice with many-to-one branches |
| Subtype/detail tables | A choice with one-to-one or one-to-many branches |
| Conditionally required data | A nonempty branch and an explicit empty branch |
| Multiple requirements for a type | Multiple relations in one branch |
| Polymorphic many-to-many | A choice plus an explicit junction table |
| Tenant-qualified references | Composite relation keys |
| Multiple roles for the same table | Requires role identities and role-specific overrides |
| Sharing one parent across several paths | Requires explicit row identity and reuse semantics |
| Partially overlapping keys across independent relations | Requires cross-relation constraint resolution |
| Recursive hierarchies and cyclic graphs | Requires explicit depth, termination and reuse semantics |
| Related-row filters such as tenant and active status | Requires relation-specific value constraints |
| Ranges, multiple-column conditions and latest-row selection | Requires predicate or derived-relation support |

Existing cycle restrictions remain. Repeated traversal through multiple roles
raises `RecursiveRelationException` rather than recursing without a bound.
Optional direct self-relations retain their existing bounded behavior. Choices
do not implement arbitrary SQL predicates, callbacks, automatic parent reuse,
UNIQUE/CHECK constraint solving, or database writes.
