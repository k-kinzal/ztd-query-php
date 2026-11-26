"""Independent signed comparison shapes, interval evaluation and typed observations."""

from itertools import product
from operator import eq, ge, gt, le, lt, ne
from random import Random
import json

import mysql_signed_membership_cases as signed

CMP, BETWEEN, TRUTH = "comparison", "signed-range", "mysql-truth-test"
OPERATIONS = {"=":eq, "<>":ne, "<":lt, "<=":le, ">":gt, ">=":ge}
SYMBOLS = (*OPERATIONS, "<=>")
NIL = ("null",)
VALUES = (NIL, tuple(signed.literal(2)), tuple(signed.literal(2147483648)))
WIDTHS = tuple(tuple(signed.reference(slot)) for slot in range(5))
SOURCES = ("unit","five-widths")
PLACEMENTS = ("projection","filter")
COMPOSED_HOSTS = (
    ("unit","projection"),("unit","filter"),("five-widths","projection"),
    ("five-widths","filter"),("five-widths","join"),("five-widths","update"),("five-widths","delete"),
)
TRUTH_FORMS = ("TRUE","FALSE","UNKNOWN","NOT TRUE","NOT FALSE","NOT UNKNOWN")
NULL_INSPECTION_COHORT = {"mysql-5.6.51","mysql-5.7.44"}


def compare(operator,left,right):
    return CMP,operator,left,right


def interval(negative,subject,lower,upper):
    return BETWEEN,negative,subject,lower,upper


def composed_operands(scope,role):
    if scope=="unit":
        return VALUES[1:]
    if role=="join":
        return WIDTHS[0],tuple(signed.reference(3,1))
    return WIDTHS[0],WIDTHS[4]


def composed_grid(spec):
    domain=spec["finite"]["nullSafeComposition"]
    for scope,role in map(tuple,domain["hosts"]):
        left,right=composed_operands(scope,role)
        for a,b in product((NIL,left),(NIL,right)):
            test=compare("<=>",a,b)
            for operator in domain["truthForms"]:
                yield scope,role,(TRUTH,operator,test)
            for negated in domain["nullPolarities"]:
                yield scope,role,("null-test",negated,test)


def grid(spec):
    defined = spec["finite"]
    values = dict(zip(("NULL","2","2147483648"),VALUES,strict=True))
    fields = dict(zip(signed.WIDTHS,WIDTHS,strict=True))
    literals = tuple(values[value] for value in defined["values"])
    sources = tuple(fields[width] for width in defined["physicalWidths"])
    for scope,role in product(defined["sources"],defined["placements"]):
        atoms = literals if scope=="unit" else (*literals,*sources)
        for a,b in product(atoms,repeat=2):
            for operation in defined["operators"]:
                yield scope,role,compare(operation,a,b)
        for a,b,c in product(atoms,repeat=3):
            for polarity in defined["rangeNegations"]:
                yield scope,role,interval(polarity,a,b,c)
    yield from composed_grid(spec)


def independent_ids():
    expected=set()
    for kind,columns in (("unit",VALUES),("five-widths",(*VALUES,*WIDTHS))):
        radix=len(columns)
        for operation,arity in ((*[(verb,2) for verb in SYMBOLS],(False,3),(True,3))):
            for code in range(radix**arity):
                operands=[]
                for _ in range(arity):
                    code,index=divmod(code,radix)
                    operands.append(columns[index])
                entry=compare(operation,*operands) if arity==2 else interval(operation,*operands)
                expected.update((kind,role,entry) for role in PLACEMENTS)
    for scope,role in COMPOSED_HOSTS:
        left,right=composed_operands(scope,role)
        for mask in range(4):
            comparison=(CMP,"<=>",left if mask & 1 else NIL,right if mask & 2 else NIL)
            expected.update((scope,role,(TRUTH,test,comparison)) for test in TRUTH_FORMS)
            expected.add((scope,role,("null-test",False,comparison)))
            expected.add((scope,role,("null-test",True,comparison)))
    return expected


def trial(tag,source,role,term,order=None):
    return signed.case(tag,source,role,json.loads(json.dumps(term)),order=order)


def render(term,joined):
    kind=term[0]
    if kind==CMP:
        return f"({render(term[2],joined)} {term[1]} {render(term[3],joined)})"
    if kind==BETWEEN:
        _,negated,subject,lower,upper=term
        return f"({render(subject,joined)} {'NOT ' if negated else ''}BETWEEN {render(lower,joined)} AND {render(upper,joined)})"
    if kind==TRUTH:
        return f"({render(term[2],joined)} IS {term[1]})"
    if kind=="logic":
        return f"({render(term[2],joined)} {term[1]} {render(term[3],joined)})"
    if kind=="null-test":
        return f"({render(term[2],joined)} IS {'NOT ' if term[1] else ''}NULL)"
    return signed.render(term,joined)


def consume(term,coordinates):
    kind=term[0]
    if kind==CMP:
        def expected_atom(leaf):
            value=consume(leaf,coordinates)
            if value[0]=="range-integer" and -2147483648<=int(value[1])<=2147483647:
                return ["integer",*value[1:]]
            return value
        return [kind,term[1],expected_atom(term[2]),expected_atom(term[3])]
    if kind in (BETWEEN,TRUTH,"logic","null-test"):
        return [kind,term[1],*(consume(child,coordinates) for child in term[2:])]
    return signed.allocated(term,coordinates)


def command(item):
    return signed.sql(item,renderer=render)


def analyze(item):
    return {"dialect":"mysql","release":item["tag"],"sql":command(item),"declarations":signed.declarations(),
            "currentDatabase":signed.DATABASE,"observeExactIntegralSlots":True}


def direct(item):
    return signed.direct(item,allocator=consume)


def graph(item):
    def observed(node):
        if isinstance(node,dict):
            return {key:observed(value) for key,value in node.items()}
        if isinstance(node,list):
            if len(node)==4 and node[0]=="integer" and node[2:]==[0,False]:
                return node[:2]
            return [observed(child) for child in node]
        return node

    result={key:observed(value) for key,value in direct(item).items()
            if key not in ("dialect","release","kind","observeExactIntegralSlots")}
    if "orderBy" in result:
        result["orderBy"]=[[slot,desc,not desc] for slot,desc in item["orderBy"]]
    return result


def physical(term,source):
    kind=term[0]
    if kind in (CMP,BETWEEN,TRUTH,"null-test"):
        return kind,term[1],*(physical(child,source) for child in term[2:])
    if kind in ("slot","integral-slot") and "columns" not in source:
        occurrence=term[2] if len(term)==3 else 0
        source=source["left"] if occurrence==0 else source["right"]
    if kind=="integer":
        return tuple(signed.literal(*term[1:]))
    return signed.observed_physical(term,source)


def null_inspection(term,row,tag):
    if tag in NULL_INSPECTION_COHORT and term[0]==CMP and term[1]=="<=>":
        return any(evaluate(value,row,tag) is None for value in term[2:])
    return evaluate(term,row,tag) is None


def evaluate(term,row,tag):
    kind=term[0]
    if kind==CMP:
        left,right=(evaluate(value,row,tag) for value in term[2:])
        if term[1]=="<=>":
            return int(left==right)
        return None if left is None or right is None else int(OPERATIONS[term[1]](left,right))
    if kind==BETWEEN:
        subject,lower,upper=(evaluate(value,row,tag) for value in term[2:])
        if subject is None:
            return None
        if (lower is not None and subject<lower) or (upper is not None and subject>upper):
            return int(term[1])
        return None if lower is None or upper is None else int(not term[1])
    if kind==TRUTH:
        operator=term[1]
        if operator=="NOT UNKNOWN":
            return int(not null_inspection(term[2],row,tag))
        tested=evaluate(term[2],row,tag)
        state="UNKNOWN" if tested is None else "TRUE" if tested else "FALSE"
        return int(state!=operator[4:] if operator.startswith("NOT ") else state==operator)
    if kind=="logic":
        left,right=(evaluate(value,row,tag) for value in term[2:])
        stable=int(term[1]=="OR")
        return stable if stable in (left,right) else None if None in (left,right) else 1-stable
    if kind=="null-test":
        if term[1]:
            return int(not null_inspection(term[2],row,tag))
        return int(evaluate(term[2],row,tag) is None)
    return signed.evaluate(term,row)


def allows_null(term):
    kind=term[0]
    if kind=="null":
        return True
    if kind in ("slot","integral-slot"):
        owner=signed.RIGHT if len(term)==3 and term[2]==1 else signed.LEFT
        return signed.NULLABILITY[owner][term[1]]
    if kind==CMP:
        return term[1]!="<=>" and any(allows_null(value) for value in term[2:])
    if kind in (BETWEEN,"logic"):
        return any(allows_null(value) for value in term[2:])
    if kind in ("range-integer","integer",TRUTH,"null-test"):
        return False
    raise ValueError(term)


def expected(item):
    result=signed.expected(item,evaluator=lambda term,row:evaluate(term,row,item["tag"]))
    if item["placement"]=="projection":
        result["fields"]=[[label,native,allows_null(item["term"])] for label,native in result["fields"]]
    return result


def generated(tag,seed,count):
    rng=Random(seed)
    extra=tuple(tuple(token) for token in (signed.literal(0,2,True),signed.literal(7,3),signed.literal(-9223372036854775808),
                signed.literal(9223372036854775807),signed.literal(-2147483649),signed.literal(-2147483648),
                signed.literal(32767),signed.literal(-8388608)))
    for index in range(count):
        role=("projection","filter","join","update","delete")[index%5]
        source="unit" if role=="projection" and index%2==0 else "five-widths"
        atoms=(*VALUES,*extra)
        if source!="unit":
            atoms=(*atoms,*WIDTHS)
        if role=="join":
            atoms=(*atoms,*(tuple(signed.reference(slot,1)) for slot in range(5)))
        def next_test():
            operands=rng.choices(atoms,k=3)
            if rng.getrandbits(1):
                return compare(rng.choice(SYMBOLS),*operands[:2])
            return interval(bool(rng.getrandbits(1)),*operands)
        term=next_test()
        if index%3==0:
            term=(TRUTH,rng.choice(("TRUE","FALSE","UNKNOWN","NOT UNKNOWN")),term)
        elif index%3==1 and role!="projection":
            term=("logic",rng.choice(("AND","OR")),term,next_test())
        order=[[1,bool(rng.getrandbits(1))]] if role=="filter" and index%2 else None
        yield trial(tag,source,role,term,order)
