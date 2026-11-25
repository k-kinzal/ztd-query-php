"""Independent native signed truth classifier and fixed structural coordinates."""

from itertools import product
from random import Random
import json

import mysql_signed_membership_cases as signed

TERM = "mysql-truth-test"
OPERATORS = ("TRUE", "FALSE", "UNKNOWN", "NOT TRUE", "NOT FALSE", "NOT UNKNOWN")
SOURCES = ("unit", "five-widths")
PLACEMENTS = ("projection", "filter")
BASICS = (("null",), ("range-integer", "0", 0, False), ("range-integer", "2", 0, False))
CHOICES = ((BASICS[0],), (BASICS[2],), (BASICS[2],BASICS[0]))
PHYSICAL = tuple(tuple(signed.reference(i)) for i in range(5))


def wrap(operator, value):
    return TERM, operator, value


def grid(spec):
    domain = spec["finite"]
    literal = dict(zip(("NULL","0","2"),BASICS,strict=True))
    storage = dict(zip(signed.WIDTHS,PHYSICAL,strict=True))
    physical = tuple(storage[kind] for kind in domain["physicalWidths"])
    atom = tuple(literal[symbol] for symbol in domain["atoms"])
    for source, role, operator in product(domain["sources"],domain["placements"],domain["operators"]):
        for operand in atom if source == "unit" else (*atom,*physical):
            yield source,role,wrap(operator,operand)
            for polarity, choices in product((False,True),CHOICES):
                yield source,role,wrap(operator,("signed-membership",polarity,operand,choices))
            for nested in domain["operators"]:
                yield source,role,wrap(operator,wrap(nested,operand))


def independent_ids():
    ids = set()
    for source, alphabet in (("unit",BASICS),("five-widths",(*BASICS,*PHYSICAL))):
        for rank in range(len(alphabet)*2*6*13):
            rank,operand = divmod(rank,len(alphabet))
            rank,operator = divmod(rank,6)
            rank,role = divmod(rank,2)
            value = alphabet[operand]
            if rank in range(1,7):
                polarity,choices = divmod(rank-1,3)
                value = "signed-membership",bool(polarity),value,CHOICES[choices]
            elif rank:
                value = wrap(OPERATORS[rank-7],value)
            ids.add((source,PLACEMENTS[role],wrap(OPERATORS[operator],value)))
    return ids


def case(tag, source, role, term, order=None):
    return signed.case(tag,source,role,json.loads(json.dumps(term)),order=order)


def command(item):
    return signed.sql(item,renderer=render)


def analyze(item):
    return {"dialect":"mysql","release":item["tag"],"sql":command(item),"declarations":signed.declarations(),
            "currentDatabase":signed.DATABASE,"observeExactIntegralSlots":True}


def direct(item):
    return signed.direct(item,allocator=allocated)


def graph(item):
    result = {key:value for key,value in direct(item).items()
              if key not in ("dialect","release","kind","observeExactIntegralSlots")}
    if "orderBy" in result:
        result["orderBy"] = [[slot, desc, not desc] for slot,desc in result["orderBy"]]
    return result


def render(term,joined):
    kind = term[0]
    if kind == TERM:
        return f"({render(term[2],joined)} IS {term[1]})"
    if kind == "null-test":
        return f"({render(term[2],joined)} IS {'NOT ' if term[1] else ''}NULL)"
    if kind == "logic":
        return f"({render(term[2],joined)} {term[1]} {render(term[3],joined)})"
    if kind == "boolean":
        return "TRUE" if term[1] else "FALSE"
    return signed.render(term,joined)


def allocated(term,coordinates):
    kind = term[0]
    if kind in (TERM,"null-test"):
        return [kind,term[1],allocated(term[2],coordinates)]
    if kind == "logic":
        return [kind,term[1],allocated(term[2],coordinates),allocated(term[3],coordinates)]
    return signed.allocated(term,coordinates)


def physical(term,source):
    kind = term[0]
    if kind == TERM:
        return kind,term[1],physical(term[2],source)
    if kind == "signed-membership":
        return kind,term[1],physical(term[2],source),tuple(physical(x,source) for x in term[3])
    return signed.observed_physical(term,source)


def evaluate(term,row):
    kind = term[0]
    if kind == TERM:
        observed = evaluate(term[2],row)
        truth = "UNKNOWN" if observed is None else "TRUE" if observed != 0 else "FALSE"
        operator = term[1]
        return int(truth != operator[4:] if operator.startswith("NOT ") else truth == operator)
    if kind == "logic":
        left,right = evaluate(term[2],row),evaluate(term[3],row)
        decided = int(term[1] == "OR")
        return decided if decided in (left,right) else None if left is None or right is None else 1-decided
    if kind == "null-test":
        return int((evaluate(term[2],row) is None) != term[1])
    return signed.evaluate(term,row)


def expected(item):
    result = signed.expected(item,evaluator=evaluate)
    if item["placement"] == "projection":
        result["fields"] = [[label,name,False] for label,name in result["fields"]]
    return result


def generated(tag,seed,count):
    rng=Random(seed)
    placements=(*PLACEMENTS,"join","update","delete")
    base=[*BASICS, ("boolean",True),("boolean",False),tuple(signed.literal(0,2,True)),
          tuple(signed.literal(4,3)),tuple(signed.literal(-9223372036854775808)),tuple(signed.literal(9223372036854775807))]
    for index in range(count):
        role=placements[index%len(placements)]
        source="unit" if role=="projection" and index%2==0 else "five-widths"
        available=list(base if source=="unit" else (*base,*PHYSICAL))
        if role=="join":
            available.extend(tuple(signed.reference(i,1)) for i in range(5))
        leaf=rng.choice(available)
        if index%3==1:
            signed_values=tuple(x for x in available if x[0]!="boolean")
            leaf=("signed-membership",bool(rng.getrandbits(1)),rng.choice(signed_values),tuple(rng.choices(signed_values,k=rng.randint(1,5))))
        if index%3==2:
            leaf=("logic",rng.choice(("AND","OR")),wrap(rng.choice(OPERATORS),leaf),wrap(rng.choice(OPERATORS),rng.choice(available)))
        if index%5==2:
            leaf=("null-test",bool(rng.getrandbits(1)),wrap(rng.choice(OPERATORS),leaf))
        for _ in range(rng.randint(1,5)):
            leaf=wrap(rng.choice(OPERATORS),leaf)
        order = [[1,bool(rng.getrandbits(1))]] if role=="filter" and index%2 else None
        yield case(tag,source,role,leaf,order)
