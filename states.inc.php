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

/*
   Game state machine is a tool used to facilitate game developement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

/*  From Game.php _Construct
    "Initial_Draw" => 10,
    "Active_Draw" => 11,
    "Free_Action" => 20,
    "Active_Turn" => 30,
    "Non-active_Turn" => 31,
    "Global_Option" => 32,
    "Card_Effect" => 33,
    "Convert" => 40,
    "Gain_Prayer" => 50,
    "Eliminate_Players" => 60,
    "Starting_player" => 70,
    */

$machinestates = [

    // The initial state. Please do not modify.

    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["Initial_Draw" => 10]
    ),
    10 => [
        "name" => "Initial_Draw",
        "description" => 'All players pick a combination of five bonus and disaster cards',
        "type" => "multipleactiveplayer",
        "action" => "stMakeEveryoneActive",
        //"args" => "",
        "possibleactions" => "stInitialDraw","actionCancel",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 20]
    ],
/*     
    use to ignore buggy multiactive state
    10 => [
        "name" => "Initial_Draw",
        "description" => 'All players pick a combination of five bonus and disaster cards',
        "descriptionmyturn" => "Pick a combination of five cards",
        "type" => "activeplayer",
        "action" => "stInitialDraw",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 20]
    ], */
    11 => [
        "name" => "Active_Draw",
        "description" => clienttranslate('${actplayer} must draw cards'),
        "descriptionmyturn" => "Pick a combination of cards to take",
        "type" => "activeplayer",
        "action" => "stActiveDraw",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 20, "Active_Turn" => 30]
    ],
    20 => [
        "name" => "Free_Action",
        "description" => clienttranslate('${actplayer} is selecting their action'),
        "descriptionmyturn" => "Select one of the free actions",
        "type" => "activeplayer",
        "action" => "stFreeAction",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 20, "Active_Turn" => 30, "Convert" => 40]
    ],
    30 => [
        "name" => "Active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "activeplayer",
        "action" => "stActiveTurn",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Non-active_Turn" => 31, "Card_Effect" => 33]
    ],
    31 => [
        "name" => "Non-active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "activeplayer",
        "action" => "stNonActiveTurn",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Active_Turn" => 30, "Non-active_Turn" => 31]
    ],
    33 => [
        "name" => "Card_Effect",
        "description" => 'Card effects are resolved sequentially',
        "type" => "game",
        "action" => "stCardEffect",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Card_Effect" => 33, "Convert" => 40]
    ],
    40 => [
        "name" => "Convert",
        "description" => "Round ended and families convert based on happiness",
        "type" => "game",
        "action" => "stConvert",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Gain_Prayer" => 50]
    ],
    50 => [
        "name" => "Gain_Prayer",
        "description" => 'Players gain prayer based on family count',
        "type" => "game",
        "action" => "stPrayer",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Endgame_Checks" => 60]
    ],
    60 => [
        "name" => "Endgame_Checks",
        "description" => 'Checking for eliminations and a winner/tie',
        "type" => "game",
        "action" => "stEndChecks",
        //"args" => "",
        "updateGameProgression" => true,
        "transitions" => ["Next_player" => 70, "End" => 70]
    ],
    70 => [
        "name" => "Next_player",
        "description" => 'Player order changes',
        "type" => "game",
        "action" => "stNextRound",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["New_Cycle" => 11]
    ],
    70 => [
        "name" => "End",
        "description" => 'calculate stats and final scores?',
        "type" => "game",
        "updateGameProgression" => false,
        "transitions" => ["gameEnd" => 99]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];



