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
    "Draw" => 10,
    "Free_Action" => 20,
    "Active_Turn" => 30,
    "Non-active_Turn" => 31,
    "Card_Effect" => 32,
    "Continue_Turn" => 33,
    "Convert" => 40,
    "Gain_Prayer" => 50,
    "Eliminate_Players" => 60,
    "Check_Winner" => 61,
    "Check_Tie" => 62,
    "Active_Player_Increment" => 70,
    "End_Game" => 89 */

$machinestates = [

    // The initial state. Please do not modify.

    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["Starting_Draw" => 10]
    ),

    10 => [
        "name" => "Initial_Draw",
        "description" => 'All players pick a combination of five bonus and disaster cards',
        "type" => "multipleactiveplayer",
        "action" => "stInitialDraw",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Cards_Selected" => 20]
    ],
    11 => [
        "name" => "Active_Draw",
        "description" => clienttranslate('${actplayer} must draw cards'),
        "descriptionmyturn" => "Pick a combination of cards to take",
        "type" => "activeplayer",
        "action" => "stActiveDraw",
        "args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Next_Player_Free" => 20, "Begin_Round" => 30]
    ],
    20 => [
        "name" => "Free_Action",
        "description" => clienttranslate('${actplayer} is selecting their action'),
        "descriptionmyturn" => "Select one of the free actions",
        "type" => "activeplayer",
        "action" => "stFreeAction",
        "args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Next_Player_Free" => 20, "Begin_Round" => 30, "conversion" => 40]
    ],
    30 => [
        "name" => "Active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "",
        "action" => "",
        "args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["played" => 31, "passed" => 32]
    ],
    31 => [
        "name" => "Non-active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "activeplayer",
        "action" => "",
        "args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["next round" => 30, "next round" => 30}
    ],
    32 => [
        "name" => "Global_Option",
        "description" => 'Decide to avoid or double the effect of a global disaster',
        "type" => "activeplayer",
        "action" => "",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["???" => 33]
    ],
    33 => [
        "name" => "Card_Effect",
        "description" => 'Card effects are resolved sequentially',
        "type" => "game",
        "action" => "",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["???" => 33]
    ],
    40 => [
        "name" => "Convert",
        "description" => "Round ended and families convert based on happiness",
        "type" => "game",
        "action" => "",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["pray" => 50]
    ],
    50 => [
        "name" => "Gain_Prayer",
        "description" => '',
        "type" => "game",
        "action" => "",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Endgame_Check" => 60]
    ],
    60 => [
        "name" => "Eliminate_Players",
        "description" => 'Checking for eliminations and a winner/tie',
        "type" => "game",
        "action" => "",
        "args" => "",
        "updateGameProgression" => true,
        "transitions" => ["No_Winner" => 70, "Game_Over" => 90]
    ],
    70 => [
        "name" => "Starting player",
        "description" => 'Player order changes',
        "type" => "game",
        "action" => "st70_newcycle",
        "args" => "",
        "updateGameProgression" => false,
        "transitions" => ["New_Cycle" => 11]
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



