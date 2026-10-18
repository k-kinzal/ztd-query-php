<?php

enum State { case Ready; public static function label() { return "ready"; } }
function subject() { return State::label(); }
