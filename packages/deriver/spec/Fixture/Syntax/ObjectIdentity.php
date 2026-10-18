<?php

class Box {} function subject() { $a = new Box(); $b = $a; return $a === $b; }
