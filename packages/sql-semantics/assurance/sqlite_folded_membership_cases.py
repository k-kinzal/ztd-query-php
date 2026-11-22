"""Finite numbered registrations and SQL that discards an otherwise active expression."""

from itertools import product
from random import Random

from run_sqlite_scalars import query
from sqlite_between_cases import expected
from sqlite_relational_cases import children
from sqlite_result_cases import integer


def folded(matches, *labels):
    return ["folded-membership", matches, list(labels)]


def fields(item):
    return item["rows"] if item.get("operation") == "values" else [[expr for expr, _ in item["columns"]]]


def finite_cases(domain):
    for root, matches, length in product(domain["roots"], domain["values"], domain["lengths"]):
        for labels in product(domain["labels"], repeat=length):
            yield query(root, [[folded(matches, *labels)]])


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    _, matches, labels = fields(reply)[0][0]
    ordinal = 0
    for label in labels:
        ordinal = ordinal * len(domain["labels"]) + domain["labels"].index(label)
    return root, matches, len(labels), ordinal


def finite_ids(domain):
    return {(root, matches, length, offset) for root in domain["roots"] for matches in domain["values"]
            for length in domain["lengths"] for offset in range(len(domain["labels"]) ** length)}


def seeded(domain, seed, count):
    rng = Random(seed)
    pool = [*domain["labels"], "03", "3", "11"]

    def decide():
        labels = [rng.choice(pool) for _ in range(rng.randrange(5))]
        return folded(bool(rng.getrandbits(1)), *labels)

    def scalar(depth):
        if depth and rng.randrange(3) == 0:
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), decide()]
        return rng.choice((decide(), ["parameter", rng.choice(pool)], ["text", "?17"]))

    for _ in range(count):
        root = rng.choice(domain["roots"])
        height = rng.randrange(1, 4) if root == "values" else 1
        width = rng.randrange(1, 5)
        rows = [[decide(), *[scalar(rng.randrange(1, 4)) for _ in range(width - 1)]] for _ in range(height)]
        labels = [rng.choice(("same", "same", 'bind"role', "値")) for _ in range(width)]
        item = query(root, rows, labels)
        item["bindings"] = {slot: rng.choice((None, -1, 0, 2, "4")) for slot in item["bindings"]}
        yield item


def boundaries():
    yield query("select", [[["parameter", "2"], folded(False, "02", "7", "2"), ["parameter", "7"]]])
    yield query("values", [[folded(True, "00032766", "02", "7")], [folded(False, "07", "2")]])
    yield query("select", [[["arithmetic", "+", integer(4), folded(True, "02", "2")]]])


def analyzed():
    yield ("SELECT missing IN () AS result", query("select", [[folded(False)]], ["result"]))
    yield ("SELECT (missing + ?02 + ?2 + ?7) NOT IN () AS result",
           query("select", [[folded(True, "02", "2", "7")]], ["result"]))
    yield ("SELECT (missing + '?7' + `?11` + ?02) IN () AS result",
           query("select", [[folded(False, "02")]], ["result"]))
    yield ("SELECT ?2 AS live, (missing + ?02 + ?7) IN () AS folded, ?7 AS echoed",
           query("select", [[["parameter", "2"], folded(False, "02", "7"), ["parameter", "7"]]], ["live", "folded", "echoed"]))
    yield ("VALUES ((missing + ?02) IN (), NULL NOT IN ())",
           query("values", [[folded(False, "02"), folded(True)]]))
    yield ("SELECT missing IN ()", query("select", [[folded(False)]], ["missing IN ()"]))


def negatives():
    yield ({"source": None, "columns": [[["folded-membership", False, {"bad": "2"}], "x"]], "bindings": {}},
           "rejected", "Expected an ordered list of numbered registrations")
    yield query("select", [[folded(False, "32767")]]), "rejected", "Parameter slot must be between 1 and 32766."
    yield {"sql": "SELECT missing IN (2) AS x", "declarations": []}, "analysis-rejected", "Column missing does not exist."
    yield {"sql": "SELECT (missing + :name) IN () AS x", "declarations": []}, "rejected", "An empty membership requires numbered bindings."


def forms(reply):
    yield "root/" + reply.get("operation", "select")
    for row in fields(reply):
        for expression in row:
            for operand in children(expression):
                if operand[0] != "folded-membership":
                    continue
                _, matches, labels = operand
                yield f"match/{matches}"
                yield f"size/{len(labels)}"
                slots = [int(label) for label in labels]
                if len(slots) != len(set(slots)):
                    yield "repeated-slot"
                if operand is not expression:
                    yield "nested"
