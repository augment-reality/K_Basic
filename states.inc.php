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

$machinestates = [

    // The initial state. Please do not modify.

    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["Initial_Draw" => 10]
    ),
/*     10 => [
        "name" => "Initial_Draw",
        "description" => 'All players pick a combination of five bonus and disaster cards',
        "type" => "multipleactiveplayer",
        "action" => "stMakeEveryoneActive",
        //"args" => "",
        "possibleactions" => "stInitialDraw","actionCancel",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 20]
    ],
    
    use to ignore buggy multiactive state */
   
    10 => [
        "name" => "Initial_Draw",
        "description" => 'All players pick a combination of five bonus and disaster cards',
        "descriptionmyturn" => "Pick a combination of five cards",
        "type" => "activeplayer",
        "action" => "stInitialDraw",
        //"args" => "",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 30]
    ],
    20 => [
        "name" => "Active_Draw",
        "description" => clienttranslate('${actplayer} must draw cards'),
        "descriptionmyturn" => "Pick a combination of cards to take",
        "type" => "activeplayer",
        "action" => "stActiveDraw",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Active_Draw" => 20, "Free_Action" => 30]
    ],
    30 => [
        "name" => "Free_Action",
        "description" => clienttranslate('${actplayer} is selecting their action'),
        "descriptionmyturn" => "Select one of the free actions",
        "type" => "activeplayer",
        "action" => "stFreeAction",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Free_Action" => 30, "Active_Turn" => 40]
    ],
    40 => [
        "name" => "Active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "activeplayer",
        "action" => "stActiveTurn",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Non-active_Turn" => 50, "End_Round" => 60]
    ],
    50 => [
        "name" => "Non-active_Turn",
        "description" => clienttranslate('${actplayer} is deciding to play a card or pass'),
        "descriptionmyturn" => "Play a card or pass",
        "type" => "activeplayer",
        "action" => "stNonActiveTurn",
        //"args" => "",
        "possibleactions" => "",
        "updateGameProgression" => false,
        "transitions" => ["Non-active_Turn" => 50, "End_Round" => 60]
    ],
    60 => [
        "name" => "Card_Effects",
        "description" => 'Card effects are resolved sequentially',
        "type" => "game",
        "action" => "stCardEffect",
        //"args" => "card list",
        "updateGameProgression" => false,
        "transitions" => ["Active_Turn" => 40, "gameEnd" => 99]
    ],
    70 => [
        "name" => "End_Round",
        "description" => 'Convert Families, Gain Prayer, Check for Eliminations, Next Player or End Game',
        "type" => "game",
        "action" => "stEnd_Round",
        "updateGameProgression" => false,
        "transitions" => ['Active_Draw' => 20, "gameEnd" => 99]
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



