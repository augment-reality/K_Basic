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
            "Update_Count" => 10,
            "Active_Draw" => 11,
            "Free_Action" => 20,
            "Active_Turn" => 30,
            "Non-active_Turn" => 31,
            "Card_Effect" => 33,
            "Convert" => 40,
            "Gain_Prayer" => 50,
            "Eliminate_Players" => 60,
            "Check_Winner" => 61,
            "Check_Tie" => 62,
            "Active_Player_Increment" => 70,
            "End_Game" => 89
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

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actPlayCard(int $card_id): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // check input values
        $args = $this->argPlayerTurn();
        $playableCardsIds = $args['playableCardsIds'];
        if (!in_array($card_id, $playableCardsIds)) {
            throw new \BgaUserException('Invalid card choice');
        }

        // Add your game logic to play a card here.
        $card_name = self::$CARD_TYPES[$card_id]['card_name'];

        // Notify all players about the card played.
        $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(),
            "card_name" => $card_name,
            "card_id" => $card_id,
            "i18n" => ['card_name'],
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("playCard");
    }

////////////Game State Actions /////////////////////
/* 
    public function stGameSetup(): void
    {
        // TODO: Implement the game setup logic here.
        $this->gamestate->nextState("Initial_Draw");
    }
 */
 /*    public function stInitialDraw(): void
    {
        // TODO: Implement the initial draw logic here.
        $this->gamestate->nextState(Free_Action);
    }
 */
/*     public function stActiveDraw(): void
    {
        // TODO: Implement the active draw logic here.
        $transition = 'Free_Action';
        $transition = 'Active_Turn';
        $this->gamestate->nextState($transition);
        $this->DbQuery("UPDATE player SET free_taken = 1 WHERE player_id = $player_id");
    }
 */
    public function stFreeAction(): void
    {
        // TODO: Implement the free action logic here.

        $transition = 'Active_Turn';
        $transition = 'Convert';
        $this->gamestate->nextState($transition);
    }

    public function stActiveTurn(): void
    {
        // TODO: Implement the active turn logic here.
        //check if global card was picked
        $this->gamestate->nextState('Non-active_Turn');
    }

    public function stNonActiveTurn(): void
    {
        // TODO: Implement the non-active turn logic here.
        $transition = 'Non-active_Turn';
        //check if global card was picked
        //check if all players played
        $transition = 'Active_Turn';
        $this->gamestate->nextState($transition);
    }

    public function stCardEffect(): void
    {
        // TODO: Implement the card effect logic here.
        $transition = 'Card_Effect';
        //check if any cards remain
        $transition = 'Convert';
        $this->gamestate->nextState($transition);
    }

    public function stConvert(): void
    {
        // TODO: Implement the convert logic here.
        $this->gamestate->nextState("Gain_Prayer");
        //could combine with prayer and  endgame check once mechanics are working
    }

    public function stPrayer(): void
    {
        // TODO: Implement the prayer logic here.
        $this->gamestate->nextState("Eliminate_Players");
        //could combine with prayer and  endgame check once mechanics are working
    }

    public function stEndRound(): void
    {
        // TODO: Implement the end round logic here.
        $transition = 'Starting_player';
        //check if any cards remain
        $transition = 'Convert';
        $this->gamestate->nextState($transition);
        $this->gamestate->nextState('gameEnd');
        //could combine with prayer and  endgame check once mechanics are working
    }

    public function stNextRound(): void
    {
        // TODO: Implement the next round logic here.
        $this->gamestate->nextState("Active_Draw");
    }

/*     public function stGameEnd(): void
    {
        // TODO: Implement the game end logic here.
    } */

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

        $this->setGameStateInitialValue("Update_Count", 0);

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
}
