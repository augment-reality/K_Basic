<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * kalua implementation : ©  August Delemeester haphazardeinsteinaugdog@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\kalua;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

class Game extends \Table
{
    private static array $CARD_TYPES;

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "Initial_Draw" => 10,
            "Active_Draw" => 20,
            "Free_Action" => 30,
            "Active_Turn" => 40,
            "Non-active_Turn" => 50,
            "Card_Effect" => 60,
            "End_Round" => 70,
        ]);          

        //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards ->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards ->init( "bonus_card" );
        
/*         self::$CARD_TYPES = [
            1 => [
                "card_name" => clienttranslate('Troll'), // ...
            ],
            2 => [
                "card_name" => clienttranslate('Goblin'), // ...
            ],
            // ...
        ]; */
    }

 


    //ties to undo function in js
    function actionCancel() {
        $this->gamestate->checkPossibleAction('actionCancel');
        $this->gamestate->setPlayersMultiactive(array ($this->getCurrentPlayerId() ), 'error', false);
    }

////////////Game State Actions /////////////////////

    public function stGameSetup(): void
    {
        // Wait for each player to select five cards
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $selectedCards = $this->getSelectedCards($player_id);
            if (count($selectedCards) != 5) {
                throw new \BgaUserException($this->_("You must select exactly 5 cards"));
            }
        }

        // Proceed to the next game state
        $this->gamestate->nextState("Initial_Draw");
    }

    public function stInitialDraw(): void
    {

    }

    public function stActiveDraw(): void
    {

    }

    public function stFreeAction(): void
    {

    }

    public function stActiveTurn(): void
    {

    }

    public function stNonActiveTurn(): void
    {

    }

    public function stCardEffect(): void
    {

        //$dices[$i] = bga_rand( 1,6 );

    }

    public function stEnd_Round(): void
    {

    }

    public function stGameEnd(): void
    {
        // Initialize constants
        $players = $this->loadPlayersBasicInfos();
        $happinessScores = [];
        $converted_pool = [];

        // Collect happiness scores
        foreach ($players as $player_id => $player) {
            $happinessScores[$player_id] = (int)$this->getUniqueValueFromDB("SELECT player_happiness FROM player WHERE player_id = $player_id");
        }

        // Find lowest and highest happiness scores
        $happy_value_low = min($happinessScores);
        $happy_value_high = max($happinessScores);

        // Skip family redistribution if everyone has same happiness
        if ($happy_value_low != $happy_value_high) {
            // Send families to temporary group for redistribution
            foreach ($players as $player_id => $happiness) {
                if ($happiness == $happy_value_low) {
                    $converted_pool[] = 0;
                    // add logic to lose 2 families
                } elseif ($happiness != $happy_value_high) {
                    $converted_pool[] = 0;
                    // add logic to lose 1 family
                }
            }

            // Count number of players with highest happiness score
            $high_happiness_players = array_filter($happinessScores, function($happiness) use ($happy_value_high) {
                return $happiness == $happy_value_high;
            });
            $count_high_happiness_players = count($high_happiness_players);

            // Redistribute families
            if (count($converted_pool) >= 3 * $count_high_happiness_players) {
                foreach ($high_happiness_players as $player_id => $happiness) {
                    $this->receiveFamiliesFromPool($player_id, 3);
                }
                $this->sendFamiliesToKalua(count($converted_pool) - 3 * $count_high_happiness_players);
            } else {
                $families_per_player = intdiv(count($converted_pool), $count_high_happiness_players);
                foreach ($high_happiness_players as $player_id => $happiness) {
                    $this->receiveFamiliesFromPool($player_id, $families_per_player);
                }
                $this->sendFamiliesToKalua(count($converted_pool) % $count_high_happiness_players);
            }
        }

        // Players receive prayers (1 per 5 family, and extra if not highest)
        foreach ($players as $player_id => $happiness) {
            $family_count = $this->getFamilyCount($player_id);
            $prayers = intdiv($family_count, 5);
            if ($happiness == $happy_value_low) {
                $prayers += 4;
            } elseif ($happiness != $happy_value_high) {
                $prayers += 2;
            }
            // add $prayers to player prayer total
        }

        // Check for player elimination (no chief/families)
        foreach ($players as $player_id => $player) {
            if ($this->getFamilyCount($player_id) == 0 && $this->getChiefCount($player_id) == 0) {
                //$this->eliminatePlayer($player_id);
            }
        }

        // Check religions remaining
        if (count($this->getRemainingReligions()) == 1) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        // Change active player
        $this->activeNextPlayer();
        $this->gamestate->nextState('nextPlayer');
    }

    public function actPass(): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Notify all players about the choice to pass.
        $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} passes'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(),
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("pass");
    }

    /**
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     */
    public function argPlayerTurn(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "playableCardsIds" => [1, 2],
        ];
    }

    /**
     * Compute and return game progression (integer between 0 and 100)
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     */
    public function getGameProgression()
    {
        //get the starting number of players and divide by the current number of players
        return 0;
    }

    /**
     * Game state actions
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);
        
        $this->activeNextPlayer();

        // Go to another gamestate
        // Here, we would detect if the game is over, and in this case use "endGame" transition instead 
        $this->gamestate->nextState("nextPlayer");
    }

    /**
     * Migrate database. Don't worry about this until your game has been published on BGA.
     */
    public function upgradeTableDb($from_version)
    {

    }

    /*
     * Gather all info for current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        // //coordinates for hk token zones
        // //offset is (265-15)/10 --> 25
        // $this->hk_array = array(
        //     array(0,5),
        //     array(25,5),
        //     array(50,5)
        //     array(75,5),
        //     array(100,5),
        //     array(125,5),
        //     array(150,5),
        //     array(175,5),
        //     array(200,5),
        //     array(225,5)
        // )


        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "kalua";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // Init global values with their initial values.

        //$this->setGameStateInitialValue("Update_Count", 0);

        $disasterCards = array(
            array( 'type' => 1, 'type_arg' => 1, 'nbr' => 4 ),
            array( 'type' => 1, 'type_arg' => 2, 'nbr' => 4 ),
            array( 'type' => 1, 'type_arg' => 3, 'nbr' => 4 ),
            array( 'type' => 1, 'type_arg' => 4, 'nbr' => 4 ),
            array( 'type' => 1, 'type_arg' => 5, 'nbr' => 3 ),
            array( 'type' => 2, 'type_arg' => 6, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 7, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 8, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 9, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 10, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 11, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 12, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 13, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 14, 'nbr' => 1 ),
            array( 'type' => 2, 'type_arg' => 15, 'nbr' => 1 )
        );
        $this->disasterCards->createCards($disasterCards, 'deck');
    
        $bonusCards = array(
            array( 'type' => 1, 'type_arg' => 1, 'nbr' => 3 ),
            array( 'type' => 1, 'type_arg' => 2, 'nbr' => 3 ),
            array( 'type' => 1, 'type_arg' => 3, 'nbr' => 3 ),
            array( 'type' => 1, 'type_arg' => 4, 'nbr' => 3 ),
            array( 'type' => 1, 'type_arg' => 5, 'nbr' => 3 ),
            array( 'type' => 1, 'type_arg' => 6, 'nbr' => 3 )
        );
        $this->bonusCards->createCards($bonusCards, 'deck');

        // Init game statistics.
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);

        // TODO: Setup the initial game situation here.

        $this->activeNextPlayer();
        
/*         //https://en.doc.boardgamearena.com/Main_game_logic:_Game.php
        // Activate players for card selection once everything has been initialized and ready.
        function st_MultiPlayerInit() {
            $this->gamestate->setAllPlayersMultiactive();
        }

        //make each player inactive once all five cards are selected, then transition to next state
        function actionBla($args) {
            $this->checkAction('actionBla');
            // handle the action using $this->getCurrentPlayerId()
            $this->gamestate->setPlayerNonMultiactive( $this->getCurrentPlayerId(), 'next');
        }
 */
        }
 
    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    // set aux score (tie breaker)
    function dbSetAuxScore($player_id, $score) {
        $this->DbQuery("UPDATE player SET player_score_aux=$score WHERE player_id='$player_id'");
    }
    // set score
    function dbSetScore($player_id, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
    }



}
