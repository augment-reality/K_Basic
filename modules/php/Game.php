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

    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "Initial_Draw" => 10,
            "Active_Draw" => 20,
            // "Free_Action" => 30,
            // "Active_Turn" => 40,
            // "Non-active_Turn" => 50,
            // "Card_Effect" => 60,
            "End_Round" => 70,
        ]);          

        //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards ->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards ->init( "bonus_card" );
        
    }


////////////Game State Actions /////////////////////

    public function stGameSetup(): void
    {
        // Wait for each player to select five cards
        $players = $this->loadPlayersBasicInfos();

        // Proceed to the next game state
        $this->gamestate->nextState("Initial_Draw");
    }

    public function stInitialDraw(): void
    {
        $player_id = $this->getActivePlayerId();
        $this->notifyPlayer($player_id, "initialDraw", clienttranslate("You must pick a combination of five bonus and disaster cards"), []);
    }

    public function actDrawDisasterCard(): void
    {
        $player_id = $this->getActivePlayerId();

        // Pick a card from the disaster deck for the player
        $card = $this->disasterCards->pickCard('deck', $player_id);

        // Notify all players about the card draw, including card details
        $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a disaster card'), [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'card_id' => $card['id'],
            'card_type' => $card['type'],
            'card_type_arg' => $card['type_arg'],
        ]);
        //increment sql value for card count
        //$this->DiceRoll();
    }

    public function actDrawBonusCard(): void
    {
        $player_id = $this->getActivePlayerId();

        // Pick a card from the bonus deck for the player
        $card = $this->bonusCards->pickCard('deck', $player_id);

        // Notify all players about the card draw, including card details
        $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a bonus card'), [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'card_id' => $card['id'],
            'card_type' => $card['type'],
            'card_type_arg' => $card['type_arg'],
        ]);
    }


    public function stActiveDraw(): void
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

    public function DiceRoll()
    {
        // Roll the dice and update sql

        $dices = array();
        for( $i=1;$i<=5;$i++ )
        {
            $dices[$i] = bga_rand( 1,6 );
            self::setGameStateValue('dice'.$i, $dices[$i]);
            self::DbQuery("UPDATE dice SET dice_value = {$dices[$i]} WHERE dice_id = $i");
        }

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

    /**
     * Gather all info for current game situation (visible by the current player).
     * The method is called each time the game interface is displayed to a player, i.e.:
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        $result = [];
        $current_player_id = (int) $this->getCurrentPlayerId();
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score`, `player_family` `family`, `player_chief` `chief` FROM `player`"
        );

        // Fetch the number of atheist families from the database
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        $result["atheist_families"] = $atheistCount;

        // Fetch the dice information from the database
        $result["dices"] = $this->getCollectionFromDb(
            "SELECT `dice_id` `id`, `dice_value` `value` FROM `dice`"
        );

        return $result;
    }

    /**
     * Returns the game name.
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "kalua";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     * according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the game.
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

        // Initialize meeples for each player (families and chief)
        foreach ($players as $player_id => $player) {
            // Example: 5 families and 1 chief per player
            self::DbQuery("UPDATE player SET player_family=5, player_chief=1 WHERE player_id=$player_id");
        }

        // Initialize atheist families (e.g., 3 per player, stored in a global table or variable)
        $atheist_start = count($players) * 3;
        $this->DbQuery("INSERT INTO global (global_id, global_value) VALUES (101, $atheist_start)");

        // Init game statistics.

        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);


        // Create dice entries in the database
        {
            $dice = []; 
            $dice[] = "(1, 2, 3, 4, 5),(9, 8, 7, 6, 5)";
        }

        $sql = "INSERT INTO dice (dice_id, dice_value) VALUES ".implode(',', $dice);

        // TODO: Setup the initial game situation here.
        $this->activeNextPlayer();
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
