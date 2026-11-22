"""Generate the RETURNING layouts and compute old-row results independently."""

from itertools import product
from random import Random

from run_sqlite_delete import (
    MAIN, TEMP, PREDICATES, item, expected as delete_effect, generated, partition, typed_rows,
)

LAYOUTS = {
    "key-only": [[["column", 0], "key"]],
    "value-only": [[["column", 1], "value"]],
    "both": [[["column", 0], "key"], [["column", 1], "value"]],
    "reversed-and-repeated": [[["column", 1], "repeat"], [["column", 0], "id"], [["column", 1], "repeat"]],
    "no-returning": [],
}
FILTERS = {
    "none": (None, {}),
    "none-admitted": (["integer", "0"], {}),
    "equals-numbered-parameter": (PREDICATES[4], {"2": 1}),
    "is-null": (PREDICATES[5], {"2": None}),
}


def request(schema="main", predicate=None, bindings=None, returning=(), columns=("key", "value")):
    return {**item(schema, predicate, bindings, columns), "returning": list(returning)}


def finite_cases(domain):
    for schema, predicate, layout in product(domain["schemas"], domain["filters"], domain["layouts"]):
        yield request(schema, *FILTERS[predicate], LAYOUTS[layout])


def finite_ids(domain):
    return set(product(domain["schemas"], domain["filters"], domain["layouts"]))


def finite_id(response):
    predicate = next(key for key, (value, _) in FILTERS.items() if value == response["where"])
    layout = next(key for key, value in LAYOUTS.items() if value == response["returning"])
    return response["target"][0], predicate, layout


def seeded(seed, count):
    rng = Random(seed ^ 4123)
    for original in generated(seed, count):
        fields = [[["column", rng.randrange(2)], rng.choice(("x", "both", 'na"me', "日"))]
                  for _ in range(rng.randrange(5))]
        yield {**original, "returning": fields}


def analyzed(transition):
    yield transition["baseSuiteTransition"]["previousRejection"], request(returning=LAYOUTS["key-only"], columns=("key",))
    yield ("DELETE FROM main.ir_delete AS d WHERE d.value IS ?02 RETURNING *, key AS extra",
           request("main", ["comparison", "IS", ["column", 0], ["parameter", "02"]], {"2": "first"},
                   [[["column", 1], "key"], [["column", 0], "value"], [["column", 1], "extra"]], ("value", "key")))
    yield ('DELETE FROM ir_delete RETURNING "value" AS "label", "value", key AS "label"',
           request("temp", returning=[[["column", 0], "label"], [["column", 0], "value"], [["column", 1], "label"]],
                   columns=("value", "key")))
    yield ("DELETE FROM main.ir_delete WHERE key = 99 RETURNING key AS absent",
           request("main", ["comparison", "=", ["column", 0], ["integer", "99"]], returning=[[["column", 0], "absent"]], columns=("key",)))


def negatives(transition, declarations):
    yield request(returning=[[["column", 0, 1], "x"]]), "must read the single target occurrence"
    yield request(returning=[[["column", 2], "x"]]), "must belong to the DELETE target"
    yield request(returning=LAYOUTS["key-only"] * 2001), "can return at most 2000 fields"
    yield {"sql": "DELETE FROM main.ir_delete AS d RETURNING d.key", "declarations": declarations}, "requires an unqualified target column"
    yield {"sql": transition["baseSuiteTransition"]["negative"], "declarations": declarations}, "requires an unqualified target column"


def boundaries():
    yield request(returning=LAYOUTS["key-only"] * 2000)


def expected(request):
    effect = delete_effect(request)
    removed, _ = partition(request)
    columns = request["target"][2]
    outputs = request["returning"]
    physical_slots = [("key", "value").index(columns[expr[1]]) for expr, _ in outputs]
    effect["statement"]["names"] = [label for _, label in outputs]
    effect["statement"]["rows"] = typed_rows(tuple(row[slot] for slot in physical_slots) for row in removed) if outputs else []
    return {**effect, "restored": {"main": typed_rows(MAIN), "temp": typed_rows(TEMP), "log": []}}
