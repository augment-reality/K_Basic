<?php

/*
 * State constants
 */
const ST_BGA_GAME_SETUP = 1;

/* Game setup states */
const ST_INITIAL_DRAW = 10;
const ST_INITIAL_FINISH = 11;

/* Phase one - drawing */
const ST_PHASE_ONE_DRAW = 20;
const ST_PHASE_ONE_DONE = 21;

/* Phase two - activate leader */
const ST_PHASE_TWO_ACTIVATE_LEADER = 30;
const ST_PHASE_TWO_NEXT_PLAYER = 31;

/* Phase three - play cards */
const ST_PHASE_THREE_PLAY_CARD = 40;
const ST_PHASE_THREE_NEXT_PLAYER = 41;
const ST_PHASE_THREE_RESOLVE_CARD = 42;
const ST_PHASE_THREE_SELECT_TARGETS = 43;
const ST_PHASE_THREE_RESOLVE_AMULETS = 44;
const ST_PHASE_THREE_ROLL_DICE = 45;
const ST_PHASE_THREE_DISCARD = 46;

/* Phase four - convert */
const ST_PHASE_FOUR_CONVERT = 50;

/* Phase five - praying */
const ST_PHASE_FIVE_PRAYING = 51;

/* End game */
const ST_END_GAME = 99;

?>