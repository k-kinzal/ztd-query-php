<?php

enum State { case Ready; }
function subject() { return State::Ready->name; }
