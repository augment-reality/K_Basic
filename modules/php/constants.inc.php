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
const ST_PHASE_THREE_CHECK_GLOBAL = 41;
const ST_PHASE_THREE_NEXT_PLAYER = 42;
const ST_PHASE_THREE_RESOLVE_CARD = 43;
const ST_PHASE_THREE_SELECT_TARGETS = 44;
const ST_PHASE_THREE_RESOLVE_AMULETS = 45;
const ST_PHASE_THREE_ROLL_DICE = 46;
const ST_PHASE_THREE_DISCARD = 47;

/* Phase four - convert */
const ST_PHASE_FOUR_CONVERT_PRAY = 50;

/* Reflexive states - can be entered from multiple states */
const ST_REFLEXIVE_BUY_CARD = 80;

/* End game */
const ST_END_GAME = 99;

/* Card constants and enumerations */

const STR_CARD_TYPE_DISASTER = "disaster";
const STR_CARD_TYPE_BONUS = "bonus";

const HAND_SIZE = 2;

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
    case Fertility = 2;
    case Amulets = 3;
    case Festivities = 4;
    case NewLeader = 5;
    case DoubleHarvest = 6;
    case Temple = 7;

}



$CARD_EFFECTS = [
    CardType::GlobalDisaster->value => [
        GlobalDisasterCard::Tsunami->value => [
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
        GlobalDisasterCard::Famine->value => [
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
        GlobalDisasterCard::Floods->value => [
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
        GlobalDisasterCard::MassiveFire->value => [
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
        GlobalDisasterCard::Drought->value => [
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
        GlobalDisasterCard::Death->value => [
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
        GlobalDisasterCard::Thunderstorm->value => [
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
        GlobalDisasterCard::Revenge->value => [
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
        GlobalDisasterCard::Epidemic->value => [
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
        GlobalDisasterCard::Riots->value => [
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
    ],
    CardType::LocalDisaster->value => [
        LocalDisasterCard::Tornado->value => [
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
        LocalDisasterCard::Earthquake->value => [
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
        LocalDisasterCard::BadWeather->value => [
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
        LocalDisasterCard::Locust->value => [
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
        LocalDisasterCard::TempleDestroyed->value => [
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
    ],
    CardType::Bonus->value => [
        BonusCard::GoodWeather->value => [
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
        BonusCard::DoubleHarvest->value => [
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
        BonusCard::Fertility->value => [
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
        BonusCard::Festivities->value => [
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
        BonusCard::NewLeader->value => [
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
        BonusCard::Temple->value => [
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
        BonusCard::Amulets->value => [
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
    ],
];


?>