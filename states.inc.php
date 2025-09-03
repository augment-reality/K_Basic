<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * kalua implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * kalua game states description
 *
 */

//    !! It is not a good idea to modify this file when a game is running !!

use Bga\GameFramework\GameStateBuilder;
use Bga\GameFramework\StateType;

if (!defined('ST_PHASE_THREE_RESOLVE_AMULET')) {
   require_once("modules/php/constants.inc.php");
}


$machinestates = [

    // The initial state. Please do not modify.

    ST_BGA_GAME_SETUP => GameStateBuilder::gameSetup(ST_INITIAL_DRAW)->build(),

    /* Initial setup */
    ST_INITIAL_DRAW => GameStateBuilder::create()
        ->name('initialDraw')
        ->description(clienttranslate('All players must pick a combination of five bonus and disaster cards'))
		->descriptionmyturn(clienttranslate('${you} must pick a combination of five bonus and disaster cards'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->possibleactions([
            'actDrawCardInit',
        ])
        ->transitions([
            '' => ST_INITIAL_FINISH
        ])
        ->build(),
    
    ST_INITIAL_FINISH => GameStateBuilder::create()
        ->name('initialFinish')
        ->type(StateType::GAME)
        ->action('stInitialFinish')
        ->transitions([
            '' => ST_PHASE_ONE_DRAW
        ])
        
        ->build(),

    
    /* Draw phase */
    ST_PHASE_ONE_DRAW => GameStateBuilder::create()
        ->name('phaseOneDraw')
        ->description(clienttranslate('${actplayer} must draw cards'))
		->descriptionmyturn(clienttranslate('${you} must draw cards'))
        ->type(StateType::ACTIVE_PLAYER)
        ->possibleactions([
            'actDrawCard',
        ])
        ->transitions([
            '' => ST_PHASE_ONE_DONE
        ])
        ->build(),

    ST_PHASE_ONE_DONE => GameStateBuilder::create()
        ->name('phaseOneDone')
        ->type(StateType::GAME)
        ->action('stPhaseOneDone')
        ->transitions([
            '' => ST_PHASE_TWO_ACTIVATE_LEADER
        ])
        ->build(),

    /* Activate leader phase */
    ST_PHASE_TWO_ACTIVATE_LEADER => GameStateBuilder::create()
        ->name('phaseTwoActivateLeader')
        ->description(clienttranslate('${actplayer} must choose a leader action'))
		->descriptionmyturn(clienttranslate('${you} must choose a leader action'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stActivateLeader')
        ->possibleactions([
            'actGiveSpeech',
            'actConvertAtheists',
            'actConvertBelievers',
            'actMassiveSpeech'
        ])
        ->transitions([
            '' => ST_PHASE_TWO_NEXT_PLAYER
        ])
        ->build(),

    ST_PHASE_TWO_NEXT_PLAYER => GameStateBuilder::create()
        ->name('phaseTwoNextPlayer')
        ->type(StateType::GAME)
        ->action('stNextPlayer') /* Note - same as phase three next player */
        ->transitions([
            'nextPlayer' => ST_PHASE_TWO_ACTIVATE_LEADER,
            'phaseDone'  => ST_PHASE_THREE_PLAY_CARD
        ])
        ->build(),

    /* Play cards phase */
    ST_PHASE_THREE_PLAY_CARD => GameStateBuilder::create()
        ->name('phaseThreePlayCard')
        ->description(clienttranslate('${actplayer} must play a card'))
		->descriptionmyturn(clienttranslate('${you} must play a card'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stPlayCard')
        ->possibleactions([
            'actPlayCard',
            'actBuyCard',
            'actPlayCardPass',
            'actSayConvert'
        ])
        ->transitions([
            'nextPlayer' => ST_PHASE_THREE_NEXT_PLAYER,
            'convert' => ST_PHASE_FOUR_CONVERT
        ])
        ->build(),

    ST_PHASE_THREE_NEXT_PLAYER => GameStateBuilder::create()
        ->name('phaseThreeNextPlayer')
        ->type(StateType::GAME)
        ->action('stNextPlayer') /* Note - same as phase two next player */
        ->transitions([
            'nextPlayer' => ST_PHASE_THREE_PLAY_CARD,
            'phaseDone'  => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    ST_PHASE_THREE_RESOLVE_CARD => GameStateBuilder::create()
        ->name('phaseThreeResolveCard')
        ->type(StateType::GAME)
        ->action('stResolveCard')
        ->transitions([
            'noCards' => ST_PHASE_THREE_PLAY_CARD,
            'phaseDone'  => ST_PHASE_THREE_RESOLVE_CARD,
            'resolveAmulets' => ST_PHASE_THREE_RESOLVE_AMULETS,
            'selectTargets' => ST_PHASE_THREE_SELECT_TARGETS,
            'rollDice' => ST_PHASE_THREE_ROLL_DICE,
            'discard' => ST_PHASE_THREE_DISCARD
        ])
        ->build(),

    ST_PHASE_THREE_SELECT_TARGETS => GameStateBuilder::create()
        ->name('phaseThreeSelectTargets')
        ->description(clienttranslate('${actplayer} must select a target'))
		->descriptionmyturn(clienttranslate('${you} must select a target'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stSelectTarget')
        ->possibleactions([
            'actSelectPlayer'
        ])
        ->transitions([
            '' => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    ST_PHASE_THREE_RESOLVE_AMULETS => GameStateBuilder::create()
        ->name('phaseThreeResolveAmulets')
        ->description(clienttranslate('Some players may choose to use their amulets'))
		->descriptionmyturn(clienttranslate('${you} must choose whether to use your amulet'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->possibleactions([
            'actAmuletChoose',
        ])
        ->transitions([
            '' => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    ST_PHASE_THREE_ROLL_DICE => GameStateBuilder::create()
        ->name('phaseThreeRollDice')
        ->description(clienttranslate('Players must roll a die'))
		->descriptionmyturn(clienttranslate('${you} must roll a die'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->possibleactions([
            'actRollDie',
        ])
        ->transitions([
            '' => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    ST_PHASE_THREE_DISCARD => GameStateBuilder::create()
        ->name('phaseThreeDiscard')
        ->description(clienttranslate('Players must choose a discard'))
		->descriptionmyturn(clienttranslate('${you} must choose a card to discard'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->possibleactions([
            'actDiscard',
        ])
        ->transitions([
            '' => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    /* Convert phase */
    ST_PHASE_FOUR_CONVERT => GameStateBuilder::create()
        ->name('phaseFourConvert')
        ->type(StateType::GAME)
        ->action('stConvert')
        ->transitions([
            '' => ST_PHASE_FIVE_PRAYING
        ])
        ->build(),

    /* Praying phase */
    ST_PHASE_FIVE_PRAYING => GameStateBuilder::create()
        ->name('phaseFivePraying')
        ->type(StateType::GAME)
        ->action('stPraying')
        ->transitions([
            'nextRound' => ST_PHASE_ONE_DRAW,
            'gameOver'  => ST_END_GAME
        ])
        ->build(),


    
    
    

    

    // 1 => array(
    //     "name" => "gameSetup",
    //     "description" => "",
    //     "type" => "manager",
    //     "action" => "stGameSetup",
    //     "transitions" => ["Initial_Draw" => 10]
    // ),
   
    // 10 => [
    //     "name" => "Initial_Draw",
    //     "description" => clienttranslate('${actplayer} must pick a combination of five bonus and disaster cards'),
    //     "descriptionmyturn" => clienttranslate("Pick a combination of five bonus and disaster cards"),
    //     "type" => "activeplayer",
    //     "action" => "stInitialDraw",
    //     "possibleactions" => ["actDrawDisasterCard","actDrawBonusCard" ],
    //     "updateGameProgression" => false,
    //     "transitions" => ["Initial_Draw" => 10,"End_Round" => 70]
    // ],
    // 20 => [
    //     "name" => "Active_Draw",
    //     "description" => clienttranslate('${actplayer} must draw cards'),
    //     "descriptionmyturn" => "Pick a combination of cards to take",
    //     "type" => "activeplayer",
    //     "action" => "stActiveDraw",
    //     //"args" => "",
    //     "possibleactions" => "",
    //     "updateGameProgression" => false,
    //     "transitions" => ["Free_Action" => 20, "Active_Turn" => 30]
    // ],
    // 30 => [
    //     "name" => "Free_Action",
    //     "description" => clienttranslate('${actplayer} is selecting their action'),
    //     "descriptionmyturn" => "Select one of the free actions",
    //     "type" => "activeplayer",
    //     "action" => "stFreeAction",
    //     //"args" => "",
    //     "possibleactions" => "",
    //     "updateGameProgression" => false,
    //     "transitions" => ["Free_Action" => 30, "Active_Turn" => 40]
    // ],
    // 40 => [
    //     "name" => "Active_Turn",
    //     "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
    //     "descriptionmyturn" => "Play a card or pass",
    //     "type" => "activeplayer",
    //     "action" => "stActiveTurn",
    //     //"args" => "",
    //     "possibleactions" => "",
    //     "updateGameProgression" => false,
    //     "transitions" => ["Non-active_Turn" => 50, "End_Round" => 60]
    // ],
    // 50 => [
    //     "name" => "Non-active_Turn",
    //     "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
    //     "descriptionmyturn" => "Play a card or pass",
    //     "type" => "activeplayer",
    //     "action" => "stNonActiveTurn",
    //     //"args" => "",
    //     "possibleactions" => "",
    //     "updateGameProgression" => false,
    //     "transitions" => ["Non-active_Turn" => 50, "End_Round" => 60]
    // ],
    // 60 => [
    //     "name" => "Card_Effects",
    //     "description" => 'Card effects are resolved sequentially',
    //     "type" => "game",
    //     "action" => "stCardEffect",
    //     //"args" => "card list",
    //     "updateGameProgression" => false,
    //     "transitions" => ["Active_Turn" => 40, "gameEnd" => 99]
    // ],
    // 70 => [
    //     "name" => "End_Round",
    //     "description" => 'Convert Families, Gain Prayer, Check for Eliminations, Next Player or End Game',
    //     "type" => "game",
    //     "action" => "stEnd_Round",
    //     "updateGameProgression" => false,
    //     "transitions" => ['Initial_Draw' => 10, "gameEnd" => 99]
    // ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    // 99 => [
    //     "name" => "gameEnd",
    //     "description" => clienttranslate("End of game"),
    //     "type" => "manager",
    //     "action" => "stGameEnd",
    //     "args" => "argGameEnd"
    // ],

];



