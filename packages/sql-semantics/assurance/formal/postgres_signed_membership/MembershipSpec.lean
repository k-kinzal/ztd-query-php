namespace SqlMembership

inductive Truth where
  | yes
  | no
  | unknown

def truthOr : Truth → Truth → Truth
  | .yes, _ | _, .yes => .yes
  | .no, .no => .no
  | _, _ => .unknown

def truthAnd : Truth → Truth → Truth
  | .no, _ | _, .no => .no
  | .yes, .yes => .yes
  | _, _ => .unknown

def negate : Truth → Truth
  | .yes => .no
  | .no => .yes
  | .unknown => .unknown

def exactEqual : Option Int → Option Int → Truth
  | some left, some right => if left = right then .yes else .no
  | _, _ => .unknown

def inList (subject : Option Int) (alternatives : List (Option Int)) : Truth :=
  alternatives.foldr (fun slot acc => truthOr (exactEqual subject slot) acc) .no

def notInList (subject : Option Int) (alternatives : List (Option Int)) : Truth :=
  alternatives.foldr (fun slot acc => truthAnd (negate (exactEqual subject slot)) acc) .yes

def anyEqual (subject : Option Int) : List (Option Int) → Truth
  | [] => .no
  | slot :: remaining =>
      match exactEqual subject slot with
      | .yes => .yes
      | .no => anyEqual subject remaining
      | .unknown =>
          match anyEqual subject remaining with
          | .yes => .yes
          | _ => .unknown

end SqlMembership
