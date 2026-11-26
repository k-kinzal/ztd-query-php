"""Generate scalar CAST values independently of the candidate validator."""

from itertools import product
from random import Random

from run_sqlite_scalars import query
from sqlite_case_cases import DDL, WORDS
from sqlite_result_cases import integer


def cast(target, operand):
    return ["cast", target, operand]


def finite_cases(domain):
    for root, target, operand in product(domain["roots"], domain["targets"], domain["atoms"]):
        yield query(root, [[cast(target, operand)]])


def finite_ids(domain):
    return set(product(domain["roots"], domain["targets"], range(len(domain["atoms"]))))


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    _, target, operand = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    return root, target, domain["atoms"].index(operand)


def seeded(domain, seed, count):
    rng = Random(seed)
    atoms = domain["atoms"] + [["parameter", "02"], ["parameter", "2"], ["parameter", "9"], ["text", "é日本"],
                              ["real", "1", None, "999", False], ["blob", ""]]

    def scalar(depth):
        if not depth:
            return rng.choice(atoms)
        choice = rng.randrange(3)
        if choice == 0:
            return cast(rng.choice(domain["targets"]), scalar(depth - 1))
        if choice == 1:
            return ["case", None, [[scalar(depth - 1), scalar(depth - 1)]], scalar(depth - 1)]
        return rng.choice(atoms)

    for _ in range(count):
        root = rng.choice(domain["roots"])
        width, rows = rng.randint(1, 4), 1 if root == "select" else rng.randint(1, 3)
        operands = [[cast(rng.choice(domain["targets"]), scalar(rng.randint(0, 3))) for _ in range(width)] for _ in range(rows)]
        item = query(root, operands)
        item["bindings"] = {slot: rng.choice((None, 1, 2.5, "30e-2", "é")) for slot in item["bindings"]}
        yield item


def analyzed():
    labels = ["CAST(5 AS)", "CAST('7.0' AS FLOATING POINT)", "CAST(7 AS 'VARCHAR(8)')", "CAST(X'80FF00' AS TEXT)"]
    values = [cast("NUMERIC", integer(5)), cast("INTEGER", ["text", "7.0"]), cast("TEXT", integer(7)),
              cast("TEXT", ["blob", "80FF00"])]
    yield "SELECT " + ", ".join(labels), [], query("select", [values], labels)
    values = [["comparison", "=", cast("TEXT", integer(7)), integer(7)],
              ["comparison", "=", cast("NUMERIC", cast("TEXT", integer(7))), ["text", "7"]]]
    yield ("SELECT CAST(7 AS TEXT) = 7 AS text_affinity, CAST(CAST(7 AS TEXT) AS NUMERIC) = '7' AS number_affinity",
           [], query("select", [values], ["text_affinity", "number_affinity"]))
    value = ["case", cast("TEXT", integer(7)), [[integer(7), ["text", "kept"]]], ["text", "lost"]]
    yield "SELECT CASE CAST(7 AS TEXT) WHEN 7 THEN 'kept' ELSE 'lost' END AS simple", [], query("select", [[value]], ["simple"])
    value = cast("BLOB", cast("TEXT", ["parameter", "02"]))
    other = cast("TEXT", ["parameter", "2"])
    yield "VALUES (CAST(CAST(?02 AS TEXT) AS BLOB), CAST(?2 AS TEXT))", [], query("values", [[value, other]])
    value = cast("BLOB", ["column", 0])
    item = query("select", [[value]], ["bytes"])
    item["source"] = WORDS
    yield "SELECT CAST(word AS BLOB) AS bytes FROM ir_case_words", [DDL], item


def negatives():
    yield query("select", [[cast("TEXT", ["column", 0])]]), "rejected", "must belong to the statement source"
    value = integer(1)
    for _ in range(33):
        value = cast("INTEGER", value)
    yield query("select", [[value]]), "rejected", "Expression height or expanded node count"
    yield ({"sql": "SELECT CAST(missing AS TEXT) FROM ir_cast", "declarations": ["CREATE TABLE ir_cast (present TEXT)"]},
           "analysis-rejected", "Column missing does not exist")
    yield ({"sql": "SELECT CAST((1, 2) AS INTEGER)", "declarations": []},
           "rejected", "has no complete SQLite scalar representation")


def boundaries():
    deep = ["parameter", "02"]
    for _ in range(32):
        deep = cast("INTEGER", deep)
    yield query("select", [[deep]])
    values = [
        cast("INTEGER", ["text", "-9223372036854775809"]),
        cast("INTEGER", ["text", "9223372036854775808"]),
        cast("TEXT", ["real", "1", None, "999", False]),
        cast("INTEGER", ["real", "1", None, "999", True]),
        cast("TEXT", ["blob", "410042"]),
    ]
    yield query("select", [values])
