"""Predict native transaction programs using plain rows and checkpoint stacks."""

from itertools import product
from random import Random

COMMANDS = ("begin", "commit", "rollback", "savepoint", "release", "rollback-to")
ENDS = ("commit", "rollback")
PROGRAMS = ("ordinary", "two", "reuse")
INITIAL = [0]


def labels(engine):
    return ("p", "quo`te" if engine == "mysql" else 'quo"te', "two words")


def step(operation, argument=None):
    return [operation, argument]


def case(engine, tag, command, name=None, prefix=(), suffix=()):
    return {"dialect": engine, "release": tag, "command": command, "name": name, "prefix": list(prefix), "suffix": list(suffix)}


def checkpoint(label, program, values):
    begin = [step("begin"), step("write", values[0]), step("savepoint", label), step("write", values[1])]
    if program == "two":
        begin.extend([step("savepoint", "other"), step("write", values[2])])
    elif program == "reuse":
        begin.extend([step("savepoint", label), step("write", values[2])])
    return begin


def finite_cases(engine, tag):
    for label, end, program in product(labels(engine), ENDS, PROGRAMS):
        prefix = checkpoint(label, program, (1, 2, 3)) + [step("rollback-to", label)]
        yield case(engine, tag, end, prefix=prefix)


def finite_id(engine, response, item):
    checkpoints = [argument for operation, argument in item["prefix"] if operation == "savepoint"]
    program = 0 if len(checkpoints) == 1 else 2 if checkpoints[0] == checkpoints[1] else 1
    return labels(engine).index(checkpoints[0]), ENDS.index(response["command"]), program


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    identities = set(product(range(len(finite["labels"])), range(len(finite["ends"])), range(len(finite["programs"]))))
    if len(identities) != finite["identitiesPerRelease"]:
        raise ValueError("The finite transaction identity contract is inconsistent")
    return identities


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        command = COMMANDS[(ordinal + rng.randrange(len(COMMANDS))) % len(COMMANDS)]
        name = rng.choice(labels(engine))
        steps = checkpoint(name, rng.choice(PROGRAMS), rng.sample(range(-100, 101), 3))
        end = step(rng.choice(ENDS))
        if command == "begin":
            yield case(engine, tag, command, prefix=rng.choice(([], steps)), suffix=[step("write", rng.randrange(1, 100)), end])
        elif command in ENDS:
            yield case(engine, tag, command, prefix=steps)
        else:
            follow = [step("rollback-to", name)] if command != "release" else []
            yield case(engine, tag, command, name, steps, [step("write", rng.randrange(1, 100)), *follow, end])


def analyzed(engine, tag):
    label = labels(engine)[1]
    delimiter = chr(96) if engine == "mysql" else '"'
    quoted = delimiter + label.replace(delimiter, delimiter * 2) + delimiter
    setup = checkpoint(label, "two", (1, 2, 3))
    outcome = [step("commit")]
    examples = [
        ("BEGIN WORK", case(engine, tag, "begin", prefix=setup, suffix=[step("write", 4), *outcome])),
        ("SAVEPOINT " + quoted, case(engine, tag, "savepoint", label, setup, [step("write", 4), step("rollback-to", label), *outcome])),
        ("ROLLBACK TO " + quoted, case(engine, tag, "rollback-to", label, setup, outcome)),
        ("RELEASE SAVEPOINT " + quoted, case(engine, tag, "release", label, setup, outcome)),
        ("COMMIT AND NO CHAIN NO RELEASE" if engine == "mysql" else "END WORK AND NO CHAIN", case(engine, tag, "commit", prefix=setup)),
        ("ROLLBACK AND NO CHAIN NO RELEASE" if engine == "mysql" else "ABORT WORK AND NO CHAIN", case(engine, tag, "rollback", prefix=setup)),
    ]
    if engine == "postgres":
        examples.append(("RELEASE SAVEPOINT " + quoted, case(
            engine, tag, "release", label, checkpoint(label, "reuse", (1, 2, 3)), [step("rollback-to", label), *outcome])))
    for sql, model in examples:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": []}, model


def negatives(engine, tag):
    yield case(engine, tag, "savepoint"), "requires a name"
    yield case(engine, tag, "commit", "before"), "requires a name"
    yield case(engine, tag, "rollback-to", "x" * (65 if engine == "mysql" else 64)), "representable native label" if engine == "mysql" else "63 bytes"
    for sql, signal in (("START TRANSACTION READ ONLY", "characteristics"), ("COMMIT AND CHAIN", "cannot chain")):
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": []}, signal


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, (item, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", item, item, signal


def expected(item):
    postgres = item["dialect"] == "postgres"
    active = False
    committed = INITIAL.copy()
    working = committed
    checkpoints = []
    trace = []
    for operation, argument in [*item["prefix"], step(item["command"], item["name"]), *item["suffix"]]:
        if operation == "begin":
            if not active or not postgres:
                committed = working
                working = committed.copy()
                checkpoints.clear()
                active = True
        elif operation in ENDS:
            if operation == "commit":
                committed = working
            working = committed
            checkpoints.clear()
            active = False
        elif operation == "write":
            assert active
            working = [*working, argument]
        elif operation == "savepoint":
            assert active
            if not postgres:
                checkpoints = [(label, rows) for label, rows in checkpoints if label != argument]
            checkpoints.append((argument, working))
        else:
            assert operation in ("release", "rollback-to") and active
            index = max(i for i, (label, _) in enumerate(checkpoints) if label == argument)
            if operation == "rollback-to":
                working = checkpoints[index][1]
                del checkpoints[index + 1:]
            elif postgres:
                del checkpoints[index:]
            else:
                del checkpoints[index]
        trace.append({"active": active, "visible": sorted(working), "committed": sorted(committed)})
    return {"status": "observed", "trace": trace}


def observation(raw):
    return raw
