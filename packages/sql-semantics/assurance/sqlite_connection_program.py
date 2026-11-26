"""Read the tiny effect-free VDBE operand programs used by the connection fixtures."""

from run import Failure, require


def read_operands(program, bindings):
    instructions = {int(row[0][1]): tuple(cell[1] for cell in row[1:]) for row in program}
    registers = {}
    counter = 0
    for _ in range(len(instructions) + 1):
        require(counter in instructions, "sqlite-connection-unbounded-operand-program", counter)
        opcode, first, second, third, expression, _flags, _details = instructions[counter]
        p1, p2, p3 = int(first), int(second), int(third)
        counter += 1
        if opcode in ("Init", "Goto"):
            counter = p2
        elif opcode == "String8":
            registers[p2] = expression
        elif opcode == "Integer":
            registers[p2] = p1
        elif opcode == "Variable":
            registers[p2] = bindings.get(str(p1))
        elif opcode == "Null":
            for slot in range(p2, max(p2, p3) + 1):
                registers[slot] = None
        elif opcode == "Concat":
            registers[p3] = None if registers[p2] is None or registers[p1] is None else str(registers[p2]) + str(registers[p1])
        elif opcode == "Function":
            require(expression in ("sqlite_attach(3)", "sqlite_detach(1)"), "sqlite-connection-unexpected-native-function", expression)
            width = 3 if expression == "sqlite_attach(3)" else 1
            require(all(index in registers for index in range(p2, p2 + width)), "sqlite-connection-missing-register", registers)
            arguments = [registers[index] for index in range(p2, p2 + width)]
            return expression, arguments
        elif opcode != "Noop":
            raise Failure("sqlite-connection-unreviewed-operand-opcode", opcode)
    raise Failure("sqlite-connection-unbounded-operand-program")


def inspect_program(program, bindings, paths):
    function, arguments = read_operands(program, bindings)
    functions = [row[5][1] for row in program if row[1][1] == "Function"]
    expiry = [int(row[2][1]) for row in program if row[1][1] == "Expire"]
    require(functions == [function] and len(expiry) == 1, "sqlite-connection-unexpected-effects", [functions, expiry])
    if function == "sqlite_attach(3)":
        file = arguments[0]
        require(file is None or (isinstance(file, (str, int)) and str(file) in paths), "sqlite-connection-unsafe-path", file)
    return [function, expiry[0]], arguments
