"""Independent identities and diagnostics for resolved MySQL group names."""

from itertools import product

from mysql_grouped_having_sql import operand, quote
from server_grouped_cases import aggregate, case
from server_join_cases import compare, slot

COLLISIONS = ("qualified", "bare-lower", "bare-upper", "repeated")
CONDITIONS = ("qualified", "bare-lower", "bare-upper", "two-bare", "aggregate")
CONTROLS = ("same-field", "different-label")
NAME = "group-name"


def named(spelling):
    return [NAME, slot(0), spelling]


def physical(term):
    return term[1] if term[0] == NAME else term


def key_shapes():
    return {
        "qualified": [slot(0)],
        "bare-lower": [named("id")],
        "bare-upper": [named("ID")],
        "repeated": [named("id"), named("id")],
    }


def condition_shapes():
    threshold = ["integer", "2"]
    return {
        "qualified": compare(">", slot(0), threshold),
        "bare-lower": compare(">", named("id"), threshold),
        "bare-upper": compare(">", named("ID"), threshold),
        "two-bare": compare("<=>", named("id"), named("ID")),
        "aggregate": compare(">", aggregate("count"), threshold),
    }


def coordinates(domain):
    yield from (("collision", *axes) for axes in product(domain["groupKeys"], domain["having"]))
    yield from ((role, "qualified", "qualified") for role in domain["canonicalControls"])


def identities():
    return {("name", "collision", key, condition) for key in COLLISIONS for condition in CONDITIONS} | {
        ("name", role, "qualified", "qualified") for role in CONTROLS
    }


def trial(tag, source, role, key, condition):
    label = "n" if role == "different-label" else "id"
    output = slot(0) if role == "same-field" else aggregate("count")
    item = case("mysql", tag, source, key_shapes()[key], [[output, label]])
    item["grouped"]["having"] = condition_shapes()[condition]
    return item


def identity(shape):
    _, symbol, left, right = shape["having"]
    output, label = shape["columns"][0]
    role = "different-label" if label == "n" else "same-field" if output == slot(0) else "collision"
    keys = next(name for name, expected in key_shapes().items() if expected == shape["keys"])
    predicate = next(name for name, expected in condition_shapes().items() if expected == ["comparison", symbol, left, right])
    return "name", role, keys, predicate


def in_domain(item):
    return len(item["grouped"]["columns"]) == 1 and item["grouped"]["columns"][0][1] in ("id", "n")


def sql(item):
    query = item["grouped"]
    source = query["source"]
    columns = source["columns"]

    def term(value):
        if value[0] == NAME:
            return quote(value[2])
        if value[0] == "slot":
            return "t." + quote(columns[value[1]])
        return operand(value, columns)

    output, label = query["columns"][0]
    keys = ", ".join(map(term, query["keys"]))
    _, symbol, left, right = query["having"]
    return (f"SELECT {term(output)} AS {quote(label)} FROM {quote(source['schema'])}.{quote(source['table'])} AS t "
            f"GROUP BY {keys} HAVING ({term(left)} {symbol} {term(right)})")


def diagnostics(item):
    shape = item["grouped"]
    profile = item["release"]
    events = []
    if profile in ("mysql-5.6.51", "mysql-5.7.44"):
        events.extend((term[2], "group statement") for term in shape["keys"] if term[0] == NAME)
    if profile == "mysql-5.7.44" and shape["having"][0] == "comparison":
        events.extend((term[2], "having clause") for term in shape["having"][2:] if term[0] == NAME)
    return [["Warning", 1052, f"Column '{name}' in {clause} is ambiguous"] for name, clause in events]
