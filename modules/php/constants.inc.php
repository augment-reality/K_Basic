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

/* Card constants and enumerations */

const STR_CARD_TYPE_DISASTER = "disaster";
const STR_CARD_TYPE_BONUS = "bonus";

enum CardType: int
{
    case GlobalDisaster = 1;
    case LocalDisaster = 2;
    case Bonus = 3;
}

enum GlobalDisasterCard: int
{
    case Tsunami = 1;
    case Famine = 2;
    case Floods = 3;
    case MassiveFire = 4;
    case Drought = 5;
    case Death = 6;
    case Thunderstorm = 7;
    case Revenge = 8;
    case Epidemic = 9;
    case Riots = 10;
}

enum LocalDisasterCard: int
{
    case Tornado = 1;
    case Earthquake = 2;
    case BadWeather = 3;
    case Locust = 4;
    case TempleDestroyed = 5;
}

enum BonusCard: int
{
    case GoodWeather = 1;
    case DoubleHarvest = 2;
    case Fertility = 3;
    case Festivities = 4;
    case NewLeader = 5;
    case Temple = 6;
    case Amulets = 7;
}

?>