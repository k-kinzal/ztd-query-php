"""Independent evaluator and structural coordinates for native signed CASE."""

from itertools import product
from random import Random

import mysql_signed_membership_cases as signed

TERM = "signed-case"
LABEL = "selected"
NULL = ("null",)
NO_ELSE = None
STORAGE = (None, "TINYINT", "SMALLINT", "MEDIUMINT", "INTEGER", "BIGINT")
NATIVE = (("NULL", 0), ("TINY", 8), ("SHORT", 16), ("INT24", 24), ("LONG", 32), ("LONGLONG", 64))
PHYSICAL_WIDTHS = (1, 2, 3, 4, 5)

def literal(n, zeros=0, minus=False):
    return "range-integer", str(n), zeros, minus

FIELD = tuple(tuple(signed.reference(i)) for i in range(5))
ZERO, TWO = literal(0), literal(2)
RESULTS = (*FIELD, NULL, ZERO, TWO)
CONDITIONS = (ZERO, TWO, NULL)
SEARCH_PAIR = (FIELD[0], FIELD[2], FIELD[4], NULL, TWO)
SUBJECTS = (NULL, TWO, FIELD[0], FIELD[4])
CANDIDATE_PAIR = (NULL, FIELD[0])
SIMPLE_PAIR = (FIELD[2], FIELD[4], NULL)

def schemas():
    return (
        ("searched", (), CONDITIONS, RESULTS, 1),
        ("searched", (), CONDITIONS, SEARCH_PAIR, 2),
        ("simple", SUBJECTS, SUBJECTS, RESULTS, 1),
        ("simple", SUBJECTS, CANDIDATE_PAIR, SIMPLE_PAIR, 2),
    )

def grid(spec):
    domain = spec["finite"]
    atoms = dict(zip(signed.NAMES, FIELD, strict=True))
    atoms.update({"NULL": NULL, "0": ZERO, "2": TWO})
    for key, form, size in (("searchedSingle", "searched", 1), ("searchedPair", "searched", 2),
                            ("simpleSingle", "simple", 1), ("simplePair", "simple", 2)):
        roles = domain[key]
        subjects = tuple(atoms[token] for token in roles.get("subjects", ()))
        decisions = tuple(atoms[token] for token in roles["conditions" if not subjects else "candidates"])
        results = roles["results"]
        outputs = tuple(atoms[token] for token in (domain["resultRepresentatives"] if isinstance(results, str) else results))
        for subject, choices, then, otherwise in product(
            subjects if subjects else (None,), product(decisions, repeat=size),
            product(outputs, repeat=size), (NO_ELSE, *outputs)
        ):
            rows = tuple((form, decision, result) for decision, result in zip(choices, then, strict=True))
            yield (TERM, subject, rows, otherwise)

def independent_ids():
    result = set()
    for form, subjects, decisions, outputs, size in schemas():
        subject_pool = subjects or (None,)
        spaces = (subject_pool, *((decisions,) * size), *((outputs,) * size), (NO_ELSE, *outputs))
        count = 1
        for pool in spaces:
            count *= len(pool)
        for position in range(count):
            coordinate = []
            for pool in spaces:
                position, index = divmod(position, len(pool))
                coordinate.append(pool[index])
            pairs = tuple((form, coordinate[1+i], coordinate[1+size+i]) for i in range(size))
            result.add((TERM, coordinate[0], pairs, coordinate[-1]))
    return result

def item(tag, term, source="five-widths"):
    return {"tag":tag,"source":source,"term":json_tree(term)}

def json_tree(operand):
    return [json_tree(child) for child in operand] if isinstance(operand, tuple) else operand

def freeze(operand):
    return tuple(freeze(child) for child in operand) if isinstance(operand,list) else operand

def generated(tag, seed, count):
    rng = Random(seed)
    constants = [NULL, ZERO, TWO, ("boolean", True), ("boolean", False), literal(2,3), literal(0,2,True),
                 literal(2147483648), literal(-2147483649), literal(-9223372036854775808), literal(9223372036854775807)]

    def build(atoms, depth=0):
        simple = bool(rng.getrandbits(1))
        form = "simple" if simple else "searched"
        subject = rng.choice(atoms) if simple else None
        nested = depth < 4
        arms = []
        for index in range(rng.randint(1, 7) if depth == 0 else rng.randint(1, 2)):
            decision = rng.choice(atoms)
            if not simple and index == 0 and rng.randrange(3) == 0:
                operands = tuple(x for x in atoms if x[0] != "boolean")
                decision = ("signed-membership", bool(rng.getrandbits(1)), rng.choice(operands),
                            tuple(rng.choice(operands) for _ in range(rng.randint(1,4))))
            result = build(atoms,depth+1) if nested and rng.randrange(5) == 0 else rng.choice(atoms)
            arms.append((form, decision, result))
        otherwise = rng.choice((None, *atoms))
        return TERM, subject, tuple(arms), otherwise

    for index in range(count):
        source = ("unit", "five-widths", "join")[index % 3]
        physical = () if source == "unit" else FIELD
        if source == "join":
            physical = (*physical, *(tuple(signed.reference(i,1)) for i in range(5)))
        yield item(tag, build([*constants, *physical]), source)

def sql(term, joined):
    if term[0] == TERM:
        _, subject, arms, result = term
        start = "CASE" if subject is None else "CASE " + sql(subject, joined)
        values = [f"WHEN {sql(condition,joined)} THEN {sql(value,joined)}" for _, condition, value in arms]
        if result is not None:
            values.append("ELSE " + sql(result, joined))
        return "(" + " ".join((start, *values, "END")) + ")"
    if term[0] == "boolean":
        return "TRUE" if term[1] else "FALSE"
    return signed.render(term, joined)

def heading_sql(source):
    if source == "unit":
        return ""
    base = signed.qualified(signed.LEFT)
    if source == "join":
        base += f" AS left_input LEFT JOIN {signed.qualified(signed.RIGHT)} AS right_input ON left_input.int_value = right_input.int_value"
    return " FROM " + base

def command(trial):
    source = trial["source"]
    return f"SELECT {sql(trial['term'], source == 'join')} AS {signed.q(LABEL)}{heading_sql(source)}"

def direct(trial):
    consumed = [[], []]
    def allocate(term):
        if term is None:
            return None
        if term[0] == TERM:
            return [TERM, allocate(term[1]), [[role, allocate(test), allocate(res)] for role,test,res in term[2]], allocate(term[3])]
        return signed.allocated(term, consumed)
    result = allocate(trial["term"])
    source = trial["source"]
    if source == "join":
        left, right = (allocate(signed.reference(signed.INT, position)) for position in range(2))
        heading = {"kind":"LEFT","left":signed.heading(signed.LEFT,consumed[0]),"right":signed.heading(signed.RIGHT,consumed[1]),
                   "on":["comparison","=",left,right]}
    else:
        heading = None if source == "unit" else signed.heading(signed.LEFT,consumed[0])
    return {"dialect":"mysql","release":trial["tag"],"source":heading,"columns":[[result,LABEL]],
            "observeExactIntegralSlots":True,"observeSignedCaseLayouts":True}

def analyze(trial):
    return {"dialect":"mysql","release":trial["tag"],"sql":command(trial),"declarations":signed.declarations(),
            "currentDatabase":signed.DATABASE,"observeExactIntegralSlots":True,"observeSignedCaseLayouts":True}

def graph(trial):
    document = direct(trial)
    rank, can_null = layout(trial, trial["term"])
    return {"source":document["source"],"columns":document["columns"],"where":None,"distinct":False,"orderBy":[],"limit":None,
            "signedCaseLayouts":[{"storage":STORAGE[rank],"nullable":can_null}]}

def physical(term, observed):
    if term is None:
        return None
    if term[0] == TERM:
        return TERM, physical(term[1], observed), tuple((kind, physical(test, observed), physical(res, observed))
                                                      for kind,test,res in term[2]), physical(term[3], observed)
    return signed.observed_physical(term, observed)

def fold_width(first, following, legacy):
    if legacy and following == 3:
        if first == 5:
            return 4
        if first == 0:
            return 5
    return max(first, following)

def coordinate(term):
    occurrence = 0 if len(term) == 2 else term[2]
    return occurrence, term[1]

def layout(trial, term):
    if term[0] == TERM:
        _, subject, arms, fallback = term
        outcomes = [layout(trial, res) for _,_,res in arms]
        if fallback is not None:
            outcomes.append(layout(trial, fallback))
        width = outcomes[0][0]
        legacy = trial["tag"] in ("mysql-5.6.51", "mysql-5.7.44")
        for next_width,_ in outcomes[1:]:
            width = fold_width(width, next_width, legacy)
        nullable = fallback is None or any(flag for _,flag in outcomes)
        if trial["tag"] == "mysql-5.6.51":
            nullable = nullable or any(layout(trial,test)[1] for _,test,_ in arms)
            nullable = nullable or (subject is not None and layout(trial,subject)[1])
        return width, nullable
    if term[0] == "null":
        return 0, True
    if term[0] in ("slot", "integral-slot"):
        side, column = coordinate(term)
        return PHYSICAL_WIDTHS[column], side == 1 or signed.NULLABILITY[signed.LEFT][column]
    if term[0] == "signed-membership":
        return 5, any(layout(trial, operand)[1] for operand in (term[2],*term[3]))
    if term[0] in ("boolean","range-integer"):
        return 5, False
    raise ValueError(term)

def evaluate(term, inputs):
    if term[0] == TERM:
        _, subject, arms, fallback = term
        stable = None if subject is None else evaluate(subject, inputs)
        for role, decision, outcome in arms:
            candidate = evaluate(decision, inputs)
            match = (stable is not None and candidate == stable) if role == "simple" else bool(candidate)
            if match:
                return evaluate(outcome, inputs)
        return None if fallback is None else evaluate(fallback, inputs)
    if term[0] == "signed-membership":
        subject = evaluate(term[2], inputs)
        if subject is None:
            return None
        candidates = [evaluate(alt, inputs) for alt in term[3]]
        if subject in candidates:
            return int(not term[1])
        return None if None in candidates else int(term[1])
    if term[0] in ("slot","integral-slot"):
        side, column = coordinate(term)
        return inputs[side][column]
    if term[0] == "null":
        return None
    if term[0] in ("boolean","range-integer"):
        return int(term[1])
    raise ValueError(term)

def input_rows(source):
    if source == "unit":
        yield ()
        return
    for left in signed.RECORDS[signed.LEFT]:
        if source == "five-widths":
            yield (left,)
        else:
            found = [right for right in signed.RECORDS[signed.RIGHT] if right[signed.INT] == left[signed.INT]]
            for right in found or [(None,) * 5]:
                yield left, right

def native_result(trial):
    term = trial["term"]
    rank, nullable = layout(trial, term)
    data_type, bits = NATIVE[rank]
    rows = []
    for inputs in input_rows(trial["source"]):
        value = evaluate(term, inputs)
        if value is not None:
            half = 1 << (bits - 1)
            value = (value + half) % (2*half) - half
        rows.append([value])
    return {"status":"rows","fields":[[LABEL,data_type,nullable,False]],"rows":rows,"warnings":0}
