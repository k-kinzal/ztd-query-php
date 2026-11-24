namespace ReindexSpec

abbrev Text := List Char

def quote : Char := '"'

def escaped : Text → Text
  | [] => []
  | x :: rest =>
    if x = quote then quote :: quote :: escaped rest else x :: escaped rest

def putName (name : Text) : Text := quote :: (escaped name ++ [quote])

def addChar (c : Char) : Option (Text × Text) → Option (Text × Text)
  | none => none
  | some (name, suffix) => some (c :: name, suffix)

def scan : Text → Option (Text × Text)
  | [] => none
  | x :: rest =>
    if x = quote then
      match rest with
      | [] => some ([], [])
      | y :: tail => if y = quote then addChar quote (scan tail) else some ([], rest)
    else addChar x (scan rest)

def getName : Text → Option (Text × Text)
  | [] => none
  | x :: rest => if x = quote then scan rest else none

inductive Head where
  | index (concurrent verbose : Bool)
  | table (concurrent verbose : Bool)
  | schema (concurrent verbose : Bool)
  | database (concurrent verbose : Bool)
  | system (verbose : Bool)
  deriving DecidableEq, Repr

def Head.binary : Head → Bool
  | .index .. | .table .. => true
  | _ => false

def optionPrefix (verbose : Bool) : Text :=
  if verbose then ['(', 'V', 'E', 'R', 'B', 'O', 'S', 'E', ')', ' '] else []

def lockPrefix (concurrent : Bool) : Text :=
  if concurrent then ['C', 'O', 'N', 'C', 'U', 'R', 'R', 'E', 'N', 'T', 'L', 'Y', ' '] else []

def header (head : Head) : Text :=
  ['R', 'E', 'I', 'N', 'D', 'E', 'X', ' '] ++ match head with
    | .index c v => optionPrefix v ++ ['I', 'N', 'D', 'E', 'X', ' '] ++ lockPrefix c
    | .table c v => optionPrefix v ++ ['T', 'A', 'B', 'L', 'E', ' '] ++ lockPrefix c
    | .schema c v => optionPrefix v ++ ['S', 'C', 'H', 'E', 'M', 'A', ' '] ++ lockPrefix c
    | .database c v => optionPrefix v ++ ['D', 'A', 'T', 'A', 'B', 'A', 'S', 'E', ' '] ++ lockPrefix c
    | .system v => optionPrefix v ++ ['S', 'Y', 'S', 'T', 'E', 'M', ' ']

def opening (head : Head) : Text := header head ++ [quote]

def choices : List Head :=
  [false, true].flatMap fun v =>
    (fun c => [Head.index c v, Head.table c v, Head.schema c v, Head.database c v]) <$> [false, true]
      |>.flatten |>.append [Head.system v]

def cut : Text → Text → Option Text
  | [], text => some text
  | _ :: _, [] => none
  | x :: xs, y :: ys => if x = y then cut xs ys else none

def incompatible : Text → Text → Bool
  | x :: xs, y :: ys => if x = y then incompatible xs ys else true
  | _, _ => false

def consumeHead (text : Text) : List (Head × Text) :=
  choices.filterMap fun head => (cut (opening head) text).map (head, ·)

inductive Target where
  | one (name : Text)
  | two (schema name : Text)
  deriving DecidableEq, Repr

def putTarget : Target → Text
  | .one name => putName name
  | .two schema name => putName schema ++ '.' :: putName name

def payload (head : Head) (text : Text) : Option Target := do
  let (first, tail) ← scan text
  if head.binary then
    match tail with
    | '.' :: rest =>
      match getName rest with
      | some (second, []) => some (.two first second)
      | _ => none
    | _ => none
  else
    match tail with
    | [] => some (.one first)
    | _ => none

def identify (text : Text) : Option (Head × Target) :=
  match consumeHead text with
  | [(head, rest)] => (payload head rest).map (head, ·)
  | _ => none

def encode (head : Head) (target : Target) : Text := header head ++ putTarget target

def fits (head : Head) : Target → Prop
  | .one _ => head.binary = false
  | .two _ _ => head.binary = true

end ReindexSpec
