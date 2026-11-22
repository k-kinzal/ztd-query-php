"""Define finite requests and an independent UPDATE new-row projection."""

from itertools import product
from random import Random

from run_sqlite_delete import typed_rows
from run_sqlite_update import COLUMNS, DECLARATIONS, FIXTURE as BASE, MAIN, TEMP, case, expected as update_effect
from run_sqlite_update import physical_row, seeded as writes
from sqlite_result_cases import integer

HOOK = "END;\n"
AFTER_RIGHT = 101
assert BASE.count(HOOK) == 1
FIXTURE = "PRAGMA recursive_triggers=OFF;" + BASE.replace(
    HOOK, f"  UPDATE ir_update SET right_value = {AFTER_RIGHT} WHERE rowid = NEW.rowid;\n" + HOOK
)

ASSIGNMENTS = {
    "null target": [[0, ["null"]]],
    "swap from old row": [[0, ["column", 1]], [1, ["column", 0]]],
    "replace right value": [[1, integer(9)]],
}
LAYOUTS = {
    "absent": [],
    "computed": [[["column", 3], "after"]],
    "right and left": [[["column", 1], "r"], [["column", 0], "l"]],
    "repeated name and reference": [[["column", 3], "same"], [["column", 3], "same"], [["column", 2], "key"]],
}


def request(asgn=ASSIGNMENTS["replace right value"], schema="main", layout=LAYOUTS["right and left"],
            columns=COLUMNS, where=None, conflict="inherit", bindings=None):
    return {**case(asgn, schema, columns, where, conflict, bindings), "returning": layout}


def finite_cases(domain):
    for schema, policy, operation, layout in product(domain["schemas"], domain["policies"], domain["assignments"], domain["layouts"]):
        yield request(ASSIGNMENTS[operation], schema, LAYOUTS[layout], conflict=policy)


def finite_ids(domain):
    return set(product(domain["schemas"], domain["policies"], domain["assignments"], domain["layouts"]))


def finite_id(reply):
    assignment = next(name for name, value in ASSIGNMENTS.items() if reply["assignments"] == value)
    output = next(name for name, value in LAYOUTS.items() if reply["returning"] == value)
    return reply["target"][0], reply["conflict"], assignment, output


def seeded(seed, repetitions):
    rng = Random(seed ^ 2861)
    outputs = ("label", "label", "値", 'quo"te')
    for original in writes(seed, repetitions):
        fields = [[["column", rng.randrange(len(COLUMNS))], rng.choice(outputs)]
                  for _ in range(rng.randrange(6))]
        yield {**original, "returning": fields}


def analyzed():
    yield ("UPDATE main.ir_update AS t SET right_value = t.left_value WHERE t.key IS ?02 RETURNING *, computed AS doubled",
           request([[0, ["column", 1]]],
                   layout=[[["column", 2], "key"], [["column", 1], "left_value"], [["column", 0], "right_value"],
                           [["column", 3], "computed"], [["column", 3], "doubled"]],
                   columns=("right_value", "left_value", "key", "computed"),
                   where=["comparison", "IS", ["column", 2], ["parameter", "02"]], bindings={"2": 1}))
    yield ("UPDATE OR IGNORE ir_update SET left_value = NULL RETURNING right_value",
           request(ASSIGNMENTS["null target"], "temp", [[["column", 1], "right_value"]],
                   columns=("left_value", "right_value"), conflict="ignore"))
    yield ("UPDATE OR ABORT main.ir_update SET left_value = NULL WHERE key IS ?5 RETURNING computed AS total",
           request(ASSIGNMENTS["null target"], layout=[[["column", 2], "total"]], columns=("left_value", "key", "computed"),
                   where=["comparison", "IS", ["column", 1], ["parameter", "5"]], conflict="abort", bindings={"5": 1}))
    yield ("UPDATE main.ir_update SET right_value = ?02, right_value = ?2 WHERE key = ?5 "
           "RETURNING key AS duplicate, key AS duplicate, right_value AS written",
           request([[0, ["parameter", "02"]], [0, ["parameter", "2"]]],
                   layout=[[["column", 1], "duplicate"], [["column", 1], "duplicate"], [["column", 0], "written"]],
                   columns=("right_value", "key"),
                   where=["comparison", "=", ["column", 1], ["parameter", "5"]], bindings={"2": "x", "5": 1}))


def boundaries():
    where = ["comparison", "IS", ["column", 2], integer(2)]
    yield request(layout=LAYOUTS["computed"] * 2000, where=where)


def negatives():
    yield request(layout=[[["column", 0, 1], "wrong"]]), "must read the single target occurrence"
    yield request(layout=[[["column", 4], "absent"]]), "must belong to the UPDATE target"
    yield request(layout=LAYOUTS["computed"] * 2001), "can return at most 2000 fields"
    for item in ("a.key", "count(key)"):
        yield ({"sql": f"UPDATE main.ir_update AS a SET right_value = 9 RETURNING {item}", "declarations": DECLARATIONS},
               "requires an unqualified target column")


def expected(item):
    def modify_after(row):
        return row[0], AFTER_RIGHT, row[2]

    result = update_effect(item, modify_after)
    result["restored"] = {
        "main": typed_rows(physical_row(row) for row in MAIN),
        "temp": typed_rows(physical_row(row) for row in TEMP),
        "log": [],
    }
    return result
