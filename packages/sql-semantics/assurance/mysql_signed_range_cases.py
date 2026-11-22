"""Independent MySQL signed range schedules and finite identities."""

from itertools import product
from random import Random

from server_cases import analyzed, case, node_key, source

EXCEPTION = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
NULL = ["null"]
MINIMUM = -(2 ** 63)
MAXIMUM = 2 ** 63 - 1


def literal(value, padding=0, negative_zero=False):
    return ["range-integer", str(value), padding, negative_zero]


def signed_range(subject, lower, upper, negated=False):
    return ["signed-range", negated, subject, lower, upper]


def source_role(table):
    return "unit" if table is None else "table"


def projection(release, fields, table=None, names=None, where=None):
    return {
        **case("mysql", table, fields, names), "release": release, "where": where,
    }


def item(release, placement, expression, role):
    table = None if role == "unit" else source("mysql")
    if placement == "projection":
        return projection(release, [expression], table)
    return projection(release, [["integer", "7"]] if table is None else [["slot", 0]], table, where=expression)


def operands(choices, role):
    constants = [NULL if value is None else literal(value) for value in choices["literalValues"]]
    return constants if role == "unit" else [*constants, *(["slot", slot] for slot in choices["slots"])]


def finite(release, choices):
    for role, placement, negated in product(choices["sources"], choices["placements"], choices["negated"]):
        for subject, lower, upper in product(operands(choices, role), repeat=3):
            yield item(release, placement, signed_range(subject, lower, upper, negated), role)


def finite_id(_engine, response, _item):
    where = response["where"]
    return (source_role(response["source"]), "projection" if where is None else "where",
            node_key(response["columns"][0][0] if where is None else where))


def finite_ids(_engine, specification, _tag):
    choices = specification["finite"]
    result = set()
    for role in choices["sources"]:
        values = operands(choices, role)
        width = len(values)
        for rank in range(width ** 3):
            bounds, upper = divmod(rank, width)
            subject, lower = divmod(bounds, width)
            for placement in choices["placements"]:
                for negated in choices["negated"]:
                    result.add((role, placement, node_key(["signed-range", negated, values[subject], values[lower], values[upper]])))
    return result


def seeded(release, seed, count):
    rng = Random(seed)
    literal_values = (MINIMUM, MAXIMUM, 9007199254740992, 9007199254740993, -2147483648, 2147483647, -3, 0, 3)
    for _ in range(count):
        table = source("mysql") if rng.randrange(2) else None
        atoms = [NULL, literal(0, rng.randrange(1, 9), True)]
        atoms += [literal(value, rng.randrange(4)) for value in literal_values]
        if table is not None:
            atoms += [["slot", 0], ["slot", 1]]

        def range_predicate():
            return signed_range(*rng.choices(atoms, k=3), negated=bool(rng.randrange(2)))

        def condition(depth):
            form = rng.randrange(5) if depth else 0
            if form < 2:
                return range_predicate()
            if form == 2:
                return ["null-test", bool(rng.randrange(2)), range_predicate()]
            if form == 3:
                return ["logic", "NOT", condition(depth - 1), None]
            return ["logic", rng.choice(("AND", "OR", "XOR")), condition(depth - 1), condition(depth - 1)]

        names = [rng.choice(("range", "range", 'other`name', "範囲")) for _ in range(rng.randrange(1, 5))]
        yield projection(release, [range_predicate() for _ in names], table, names,
                         condition(rng.randrange(4)) if rng.randrange(3) else None)


def positives(release):
    yield "SELECT 2 BETWEEN 2 AND 3 AS r0", item(release, "projection", signed_range(literal(2), literal(2), literal(3)), "unit")
    yield "SELECT -00 NOT BETWEEN 01 AND 001 AS r0", item(
        release, "projection", signed_range(literal(0, 1, True), literal(1, 1), literal(1, 2), True), "unit")
    yield "SELECT NULL BETWEEN NULL AND NULL AS r0", item(release, "projection", signed_range(NULL, NULL, NULL), "unit")
    yield (
        "SELECT -9223372036854775808 BETWEEN -9223372036854775808 AND 9223372036854775807 AS r0",
        item(release, "projection", signed_range(literal(MINIMUM), literal(MINIMUM), literal(MAXIMUM)), "unit"),
    )
    yield (
        "SELECT 9007199254740993 BETWEEN 9007199254740992 AND 9007199254740992 AS r0",
        item(release, "projection", signed_range(literal(9007199254740993), literal(9007199254740992), literal(9007199254740992)), "unit"),
    )
    yield (
        "SELECT a.Id AS r0 FROM ir_fixture.ir_evidence_accounts a WHERE a.Id NOT BETWEEN a.delta AND NULL",
        item(release, "where", signed_range(["slot", 0], ["slot", 1], NULL, True), "table"),
    )
    yield (
        "SELECT a.Id BETWEEN a.delta AND 3 AS r0 FROM ir_fixture.ir_evidence_accounts a",
        item(release, "projection", signed_range(["slot", 0], ["slot", 1], literal(3)), "table"),
    )


def negatives(release):
    one, missing = literal(1), ["slot", 0]
    for triple in ((missing, one, one), (one, missing, one), (one, one, missing)):
        yield item(release, "projection", signed_range(*triple), "unit"), "A range slot must belong to the SELECT source."
    yield (item(release, "where", signed_range(one, one, ["slot", 0, 1]), "table"),
           "A predicate slot must belong to a visible input occurrence.")
    for sql, reason in (
        ("SELECT (1 + 2) BETWEEN 1 AND 3 AS r0", "A range operand requires a pure signed integer or NULL."),
        ("SELECT 1.5 BETWEEN 1 AND 3 AS r0", "The numeric literal must have the signed integer role."),
        ("SELECT 9223372036854775808 BETWEEN 1 AND 3 AS r0", "Range integer is outside the signed 64-bit range."),
    ):
        yield analyzed("mysql", sql, version=release), reason
    yield (item(release, "projection", signed_range(literal(MAXIMUM + 1), one, one), "unit"),
           "Range integer is outside the signed 64-bit range.")
    yield (item(release, "projection", signed_range(literal("3e2"), one, one), "unit"),
           "Expected a normalized signed range integer.")

    for invalid, reason in (
        (literal(MINIMUM - 1), "Range integer is outside the signed 64-bit range."),
        (literal(4, 33), "Range integer padding exceeds the effective field width."),
        (literal(4, negative_zero=True), "A separate negative sign applies only to zero."),
    ):
        yield item(release, "projection", signed_range(one, one, invalid), "unit"), reason


def requests(_engine, release, specification):
    for index, request in enumerate(finite(release, specification["finite"])):
        yield f"finite/{index}", request, request, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, request in enumerate(seeded(release, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request, request, None
    for index, (sql, request) in enumerate(positives(release)):
        yield f"analysis/{index}", analyzed("mysql", sql, version=release), request, None
    for index, (request, reason) in enumerate(negatives(release)):
        yield f"negative/{index}", request, None, {"exception": EXCEPTION, "message": reason}
