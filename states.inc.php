<?php
/*
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

$machinestates = [

    /*  From Game.php _Construct
        "Update_Count" => 10,
        "Active_Draw" => 11,
        "Free_Action" => 20,
        "Convert" => 40,
        "Gain_Prayer" => 50,
        "Check_Winner" => 61,
        "Check_Tie" => 62,
        "Active_Player_Increment" => 70,
        "End_Game" => 89 */

    10 => [
        "name" => "Update_Count",
        "description" => 'Figure out how many players there are',
        "type" => "game",
    ],
    11 => [
        "name" => "Active_Draw",
        "description" => 'Draw up to five cards',
        "type" => "game",
    ],
    20 => [
        "name" => "Free_Action",
        "description" => 'Pick one of the free actions',
        "type" => "game",
    ],

    40 => [
        "name" => "Convert",
        "description" => 'Redistribute families based on happiness, etc',
        "type" => "game",
    ],
    50 => [
        "name" => "Gain_Prayer",
        "description" => 'Get Prayer points',
        "type" => "game",
    ],

    61 => [
        "name" => "Check_Winner",
        "description" => 'Checking for a winner',
        "type" => "game",
    ],
    62 => [
        "name" => "Check_Tie",
        "description" => 'Checking if a tie-breaker is needed',
        "type" => "game",
    ],
    70 => [
        "name" => "Active_Player_Increment",
        "description" => 'Go to next player',
        "type" => "game",
    ],
    89 => [
        "name" => "End_Game",
        "description" => 'Winner has been declared. Ready to end game',
        "type" => "game",
    ],

    // The initial state. Please do not modify.

    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => 2]
    ),

    // Note: ID=2 => your first state

    2 => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card or pass'),
        "descriptionmyturn" => clienttranslate('${you} must play a card or pass'),
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => [
            // these actions are called from the front with bgaPerformAction, and matched to the function on the game.php file
            "actPlayCard", 
            "actPass",
        ],
        "transitions" => ["playCard" => 3, "pass" => 3]
    ],
    
    3 => [
        "name" => "nextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,
        "transitions" => ["endGame" => 99, "nextPlayer" => 2]
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



