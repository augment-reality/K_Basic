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
const ST_PHASE_FOUR_CONVERT_PRAY = 50;

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

$CARD_EFFECTS = [
    // Global Disasters
    'Tsunami' => [
        'prayer_cost' => 0,
        'prayer_effect' => 3,
        'happiness_effect' => -3,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Famine' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => -3,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Floods' => [
        'prayer_cost' => 0,
        'prayer_effect' => 2,
        'happiness_effect' => -2,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Massive Fire' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => -2,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Drought' => [
        'prayer_cost' => 0,
        'prayer_effect' => 3,
        'happiness_effect' => -2,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Death' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => 0,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Thunderstorm' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => -1,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Revenge' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => "roll_d6",
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Epidemic' => [
        'prayer_cost' => 0,
        'prayer_effect' => "roll_d6",
        'happiness_effect' => -1,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Riots' => [
        'prayer_cost' => 0,
        'prayer_effect' => 1,
        'happiness_effect' => 0,
        'convert_to_atheist' => 1,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 1,
        'keep_card' => 0,
    ],

    // Local Disasters
    'Tornado' => [
        'prayer_cost' => 4,
        'prayer_effect' => 0,
        'happiness_effect' => -1,
        'convert_to_atheist' => 1,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Earthquake' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => -3,
        'convert_to_atheist' => 0,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Bad Weather' => [
        'prayer_cost' => 1,
        'prayer_effect' => 0,
        'happiness_effect' => -1,
        'convert_to_atheist' => 0,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Locust' => [
        'prayer_cost' => 3,
        'prayer_effect' => 0,
        'happiness_effect' => -2,
        'convert_to_atheist' => 0,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Temple Destroyed' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => -2,
        'convert_to_atheist' => 0,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],

    // Bonus Cards
    'Good Weather' => [
        'prayer_cost' => 2,
        'prayer_effect' => 0,
        'happiness_effect' => 2,
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Double Harvest' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => 4,
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Fertility' => [
        'prayer_cost' => 6,
        'prayer_effect' => 0,
        'happiness_effect' => 1,
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => "roll_d6",
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Festivities' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => "roll_d6",
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'New Leader' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => 1,
        'convert_to_atheist' => 0,
        'family_dies' => 1,
        'convert_to_religion' => 0,
        'recover_leader' => true,
        'discard' => 0,
        'keep_card' => 0,
    ],
    'Temple' => [
        'prayer_cost' => 5,
        'prayer_effect' => 0,
        'happiness_effect' => 0,
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => true,
    ],
    'Amulets' => [
        'prayer_cost' => 4,
        'prayer_effect' => 0,
        'happiness_effect' => 0,
        'convert_to_atheist' => 0,
        'family_dies' => 0,
        'convert_to_religion' => 0,
        'recover_leader' => false,
        'discard' => 0,
        'keep_card' => true,
    ],
];

?>