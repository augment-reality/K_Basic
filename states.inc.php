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

   require_once("modules/php/constants.inc.php");


$machinestates = [

    // The initial state. Please do not modify.

    ST_BGA_GAME_SETUP => GameStateBuilder::gameSetup(ST_INITIAL_DRAW)->build(),

    /* Initial setup */
    ST_INITIAL_DRAW => GameStateBuilder::create()
        ->name('initialDraw')
        ->description(clienttranslate('All players must pick a combination of five bonus and disaster cards'))
		->descriptionmyturn(clienttranslate('${you} must pick a combination of five bonus and disaster cards'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->action('stInitialDraw')
        ->possibleactions([
            'actDrawCardInit',
        ])
        ->transitions([
            'cleanupInit' => ST_INITIAL_FINISH
        ])
        ->build(),
    
    ST_INITIAL_FINISH => GameStateBuilder::create()
        ->name('initialFinish')
        ->type(StateType::GAME)
        ->action('stInitialFinish')
        ->transitions([
            'drawToFive' => ST_PHASE_ONE_DRAW
        ])
        
        ->build(),

    
    /* Draw phase */
    ST_PHASE_ONE_DRAW => GameStateBuilder::create()
        ->name('phaseOneDraw')
        ->description(clienttranslate('${actplayer} must draw cards for round'))
		->descriptionmyturn(clienttranslate('${you} must draw cards for round'))
        ->type(StateType::ACTIVE_PLAYER)
        ->possibleactions([
            'actDrawCard',
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'FreeAction' => ST_PHASE_TWO_ACTIVATE_LEADER
        ])
        ->build(),

    /* Activate leader phase */
    ST_PHASE_TWO_ACTIVATE_LEADER => GameStateBuilder::create() //30
        ->name('phaseTwoActivateLeader')
        ->description(clienttranslate('${actplayer} must choose a leader action'))
		->descriptionmyturn(clienttranslate('${you} must choose a leader action'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stActivateLeader')
        ->args('argActivateLeader')
        ->possibleactions([
            'actSacrificeLeader',
            'actConvertAtheists',
            'actConvertBelievers',
            'actGiveSpeech',
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'nextPlayerTwo' => ST_PHASE_TWO_NEXT_PLAYER
        ])
        ->build(),

    ST_PHASE_TWO_NEXT_PLAYER => GameStateBuilder::create() //31
        ->name('phaseTwoNextPlayer')
        ->type(StateType::GAME)
        ->action('stNextPlayerLeader')
        ->transitions([
            'checkRoundTwo' => ST_PHASE_TWO_ACTIVATE_LEADER,
            'phaseTwoDone'  => ST_PHASE_THREE_PLAY_CARD
        ])
        ->build(),

    /* Play cards phase */
    ST_PHASE_THREE_PLAY_CARD => GameStateBuilder::create() //40
        ->name('phaseThreePlayCard')
        ->description(clienttranslate('${actplayer} must play a card'))
		->descriptionmyturn(clienttranslate('${you} must play a card'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stPlayCard')
        ->args('argPlayCard')
        ->possibleactions([
            'actPlayCard',
            'actPlayCardPass', //only for non-round leader
            'actSayConvert', //only for round leader
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'playAgain' => ST_PHASE_THREE_PLAY_CARD, // Allow playing multiple cards for round leader
            'phaseThreeCheckGlobal' => ST_PHASE_THREE_CHECK_GLOBAL, // Allow avoid/double option after playing a global disaster card
            'nextPlayerThree' => ST_PHASE_THREE_NEXT_PLAYER,
            'resolveCards' => ST_PHASE_THREE_RESOLVE_CARD,
            'buyCardReflex' => ST_REFLEXIVE_BUY_CARD, // Transition to reflexive state
            'convertPray' => ST_PHASE_FOUR_CONVERT_PRAY, // Transition when round leader chooses convert
        ])
        ->build(),
        
    ST_PHASE_THREE_CHECK_GLOBAL => GameStateBuilder::create() //41
        ->name('phaseThreeCheckGlobal')
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stGlobalOption')
        ->args('argGlobalOption')
        ->possibleactions([
            'actAvoidGlobal',
            'actDoubleGlobal',
            'actNormalGlobal',
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'playAgain' => ST_PHASE_THREE_PLAY_CARD,
            'nextPlayerThree' => ST_PHASE_THREE_NEXT_PLAYER
        ])
        ->build(),

    ST_PHASE_THREE_NEXT_PLAYER => GameStateBuilder::create() //42
        ->name('phaseThreeNextPlayer')
        ->type(StateType::GAME)
        ->action('stNextPlayerCards')
        ->transitions([

            'checkRoundThree' => ST_PHASE_THREE_PLAY_CARD,
            'resolveCards'  => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    ST_PHASE_THREE_RESOLVE_CARD => GameStateBuilder::create() //43
        ->name('phaseThreeResolveCard')
        ->type(StateType::GAME)
        ->action('stResolveCard')
        ->transitions([
            'continueCardPhase' => ST_PHASE_THREE_PLAY_CARD, // Return to card playing after resolving cards
            'selectTargets' => ST_PHASE_THREE_SELECT_TARGETS,
            'resolveAmulets' => ST_PHASE_THREE_RESOLVE_AMULETS,
            'rollDice' => ST_PHASE_THREE_ROLL_DICE,
            'discard' => ST_PHASE_THREE_DISCARD,
            'beginAllPlay'  => ST_PHASE_THREE_RESOLVE_CARD,
            'convertPray' => ST_PHASE_FOUR_CONVERT_PRAY, // Proceed to convert/pray after resolving cards
        ])
        ->build(),

    ST_PHASE_THREE_SELECT_TARGETS => GameStateBuilder::create()
        ->name('phaseThreeSelectTargets')
        ->description(clienttranslate('${actplayer} must select a target'))
		->descriptionmyturn(clienttranslate('${you} must select a target'))
        ->type(StateType::ACTIVE_PLAYER)
        ->action('stSelectTarget')
        ->possibleactions([
            'actSelectPlayer',
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'resolveAmulets' => ST_PHASE_THREE_RESOLVE_AMULETS,
            'rollDice' => ST_PHASE_THREE_ROLL_DICE,
            'discard' => ST_PHASE_THREE_DISCARD,
            'beginAllPlay'  => ST_PHASE_THREE_RESOLVE_CARD,
        ])
        ->build(),

    ST_PHASE_THREE_RESOLVE_AMULETS => GameStateBuilder::create()
        ->name('phaseThreeResolveAmulets')
        ->description(clienttranslate('Some players may choose to use their amulets'))
		->descriptionmyturn(clienttranslate('${you} must choose whether to use your amulet'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->action('stResolveAmulets')
        ->args('argResolveAmulets')
        ->possibleactions([
            'actAmuletChoose',
            'actGoToBuyCardReflex' // Action to enter reflexive state
        ])
        ->transitions([
            'rollDice' => ST_PHASE_THREE_ROLL_DICE,
            'discard' => ST_PHASE_THREE_DISCARD,
            'beginAllPlay'  => ST_PHASE_THREE_RESOLVE_CARD,
        ])
        ->build(),

    ST_PHASE_THREE_ROLL_DICE => GameStateBuilder::create()
        ->name('phaseThreeRollDice')
        ->description(clienttranslate('Players must roll a die'))
		->descriptionmyturn(clienttranslate('${you} must roll a die'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->action('stRollDice')
        ->possibleactions([
            'actRollDie',
        ])
        ->transitions([
            'discard' => ST_PHASE_THREE_DISCARD,
            'beginAllPlay'  => ST_PHASE_THREE_RESOLVE_CARD,
        ])
        ->build(),

    ST_PHASE_THREE_DISCARD => GameStateBuilder::create()
        ->name('phaseThreeDiscard')
        ->description(clienttranslate('Some players must discard a card (others wait)'))
		->descriptionmyturn(clienttranslate('${you} must choose a card to discard'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->action('stDiscard')
        ->possibleactions([
            'actDiscard',
        ])
        ->transitions([
            'beginAllPlay' => ST_PHASE_THREE_RESOLVE_CARD
        ])
        ->build(),

    /* Convert then pray phase */
    ST_PHASE_FOUR_CONVERT_PRAY => GameStateBuilder::create()
        ->name('phaseFourConvertPray')
        ->type(StateType::GAME)
        ->action('stConvertPray')
        ->transitions([
            'phaseOneDraw' => ST_PHASE_ONE_DRAW,
            'gameOver'  => ST_END_GAME
        ])
        ->build(),

    /* Reflexive state - Buy card anytime by spending 5 prayer points
        Currently not allowed to pick which deck type*/
    ST_REFLEXIVE_BUY_CARD => GameStateBuilder::create()
        ->name('reflexiveBuyCard')
        ->description(clienttranslate('${actplayer} may spend 5 prayer points to buy a card'))
        ->descriptionmyturn(clienttranslate('${you} may spend 5 prayer points to buy a card'))
        ->type(StateType::ACTIVE_PLAYER)
        ->possibleactions([
            'actDrawCardAnytime',
            'actCancelBuyCard'
        ])
        ->transitions([
            // No transitions - uses jumpToState to return to saved state
        ])
        ->build(),
];