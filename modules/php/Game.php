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

use BonusCard;
use CardType;
use GlobalDisasterCard;
use LocalDisasterCard;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

class Game extends \Table
{
    private static array $CARD_TYPES;

    public function __construct()
    {
        parent::__construct();

        /* Global variables */
        $this->initGameStateLabels([
            "roundLeader" => 10 /* Player who leads the current round */
        ]);          

        //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards ->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards ->init( "bonus_card" );
        
    }


////////////Game State Actions /////////////////////

    public function stInitialDraw(): void
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    public function stInitialFinish(): void
    {
        /* State to do any necessary cleanup, 
        notifications to UI (e.g. cards drawn by other players) 
        and set the active player to the first player */
        $this->trace("KALUA Made it to initial finish");

        /* Active player is already set */
        //$this->activeNextPlayer();
        $this->gamestate->nextState();
    }

    public function stPhaseOneDone(): void
    {
        /* Not much to do here for now */
        $this->gamestate->nextState();
    }

    public function stActivateLeader(): void
    {
        /* Skip handling for leader (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
        // $args = $this->argActivateLeader();
        // if ($args['_no_notify']) 
        // {
        //     /* Notify all players that the player is being skipped? */
        //     $this->gamestate->nextState('');
        // }
    }

    public function stNextPlayer(): void
    {
        /* Update the active player to next. 
        If the active player is now the trick leader (everyone has had a chance), 
        go to PLAY ACTION CARD, otherwise ACTIVATE LEADER */
        $this->activeNextPlayer();
    
        if ($this->getActivePlayerId() == $this->getGameStateValue("roundLeader"))
        {
            $this->gamestate->nextState("phaseDone");
        }
        $this->gamestate->nextState("nextPlayer");
    }

    public function stPlayCard(): void
    {
        /* skip handling for play card (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
    }

    public function stResolveCard(): void
    {
        /* Check if there is a card currently resolving (we come back to this state from others while a card is still resolving) - if so continue to resolve that card
            Else if there are cards remaining move the next available card to resolving
            Else we’re done resolving cards - set the active player to the trick leader and go to PHASE THREE PLAY ACTION CARD
            Resolve (or continue resolving) the resolving card */

    }
    
    public function stSelectTarget(): void
    {
        /* Active player must select the player to target with their disaster */
    }

    public function stConvert(): void
    {
        /* Based on rules, update family counts */
    }

    public function stPraying(): void
    {
       /* Based on rules, update praying points
    Check for end game condition
    Update trick leader to next player 
    Check that they haven’t been completely eliminated - cycle until we found someone who hasn’t 
    Set active player to selected trick leader */

    }

///////////Player Actions /////////////////////
    public function actDrawCardInit(string $type /* either "disaster" or "bonus" */): void
    {
        /* Draws a card and notifies that user of the drawn card
            Notifies UI to draw a card, and how many cards left to draw to reach 5
            UI will update with cards drawn, or waiting once we hit 0 cards remaining
            Checks if all users have drawn 5 cards - if they have, go to INITIAL FINISH */
        
        $this->drawCard_private($type);
        $player_id = $this->getCurrentPlayerId();
        
        if ($this->disasterCards->countCardInLocation("hand", $player_id) 
            + $this->bonusCards->countCardInLocation("hand", $player_id)
            == 5 )
        {
            $this->gamestate->setPlayerNonMultiactive($player_id, '');
        }
    }

    public function actDrawCard(string $type): void
    {
        /* Draws a card of the given type
            TODO what if the decks are empty? Does UI need to know that?
            Updates player with drawn card
            If player hand size is 5 or more, done drawing
            Else stay in state */
        $this->drawCard_private($type);
        $player_id = $this->getCurrentPlayerId();
        
        /* Once the player has at least five cards, move to next phase */
        if ($this->disasterCards->countCardInLocation("hand", $player_id) 
            + $this->bonusCards->countCardInLocation("hand", $player_id)
            >= 5 )
        {
            $this->gamestate->nextState();
        }
    }

    /***** Leader state actions *****/
    public function actGiveSpeech(): void
    {
        $this->trace("KALUA give speech!");

        $player_id = $this->getCurrentPlayerId();

        $this->notifyAllPlayers('giveSpeech', clienttranslate('${player_name} gave a speech'), [
                'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName()
            ]);
        
        $this->gamestate->nextState();
    }

    public function actConvertAtheists(): void
    {
        $this->trace("KALUA convert atheists!");
    }

    public function actConvertBelievers(int $target_player_id): void
    {

    }

    public function actMassiveSpeech(): void
    {
        $this->trace("KALUA give massive speech!");
    }
    /***************************************/

    /***** Play card actions ******/
    public function actPlayCard(int $card_type, int $card_id): void
    {

    }

    public function actBuyCard(): void
    {

    }

    public function actPlayCardPass(): void
    {

    }

    public function actSayConvert(): void
    {

    }
    /***************************************/

    /******** Resolve card actions ********/
    public function actSelectPlayer(int $player_id): void
    {

    }

    public function actAmuletChoose(bool $use_amulet): void
    {

    }

    public function actRollDie(int $result): void
    {

    }

    public function actDiscard(int $card_type, int $card_id): void
    {

    }
    /******************************/


    /******* Arg functions ************/
    // function argActivateLeader() : array
    // {
    //     // return [
    //     //     '_no_notify' => false /* TODO!! */
    //     // ];
    // }   
    


    // public function stGameSetup(): void
    // {
    //     // Wait for each player to select five cards
    //     $players = $this->loadPlayersBasicInfos();

    //     // Proceed to the next game state
    //     $this->gamestate->nextState("Initial_Draw");
    // }

    // public function stInitialDraw(): void
    // {
    //     $player_id = $this->getActivePlayerId();
    //     $this->notifyPlayer($player_id, "initialDraw", clienttranslate("You must pick a combination of five bonus and disaster cards"), []);
    // }

    // public function actDrawDisasterCard(): void
    // {
    //     $player_id = $this->getActivePlayerId();

    //     // Pick a card from the disaster deck for the player
    //     $card = $this->disasterCards->pickCard('deck', $player_id);

    //     // Notify all players about the card draw, including card details
    //     $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a disaster card'), [
    //         'player_id' => $player_id,
    //         'player_name' => $this->getActivePlayerName(),
    //         'card_id' => $card['id'],
    //         'card_type' => $card['type'],
    //         'card_type_arg' => $card['type_arg'],
    //     ]);
    //     //increment sql value for card count
    //     //$this->DiceRoll();
    // }

    // public function actDrawBonusCard(): void
    // {
    //     $player_id = $this->getActivePlayerId();

    //     // Pick a card from the bonus deck for the player
    //     $card = $this->bonusCards->pickCard('deck', $player_id);

    //     // Notify all players about the card draw, including card details
    //     $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a bonus card'), [
    //         'player_id' => $player_id,
    //         'player_name' => $this->getActivePlayerName(),
    //         'card_id' => $card['id'],
    //         'card_type' => $card['type'],
    //         'card_type_arg' => $card['type_arg'],
    //     ]);
    // }


    // public function stActiveDraw(): void
    // {

    // }

    // public function stGameEnd(): void
    // {
    //     // Initialize constants
    //     $players = $this->loadPlayersBasicInfos();
    //     $happinessScores = [];
    //     $converted_pool = [];

    //     // Collect happiness scores
    //     foreach ($players as $player_id => $player) {
    //         $happinessScores[$player_id] = (int)$this->getUniqueValueFromDB("SELECT player_happiness FROM player WHERE player_id = $player_id");
    //     }

    //     // Find lowest and highest happiness scores
    //     $happy_value_low = min($happinessScores);
    //     $happy_value_high = max($happinessScores);

    //     // Skip family redistribution if everyone has same happiness
    //     if ($happy_value_low != $happy_value_high) {
    //         // Send families to temporary group for redistribution
    //         foreach ($players as $player_id => $happiness) {
    //             if ($happiness == $happy_value_low) {
    //                 $converted_pool[] = 0;
    //                 // add logic to lose 2 families
    //             } elseif ($happiness != $happy_value_high) {
    //                 $converted_pool[] = 0;
    //                 // add logic to lose 1 family
    //             }
    //         }

    //         // Count number of players with highest happiness score
    //         $high_happiness_players = array_filter($happinessScores, function($happiness) use ($happy_value_high) {
    //             return $happiness == $happy_value_high;
    //         });
    //         $count_high_happiness_players = count($high_happiness_players);

    //         // Redistribute families
    //         if (count($converted_pool) >= 3 * $count_high_happiness_players) {
    //             foreach ($high_happiness_players as $player_id => $happiness) {
    //                 $this->receiveFamiliesFromPool($player_id, 3);
    //             }
    //             $this->sendFamiliesToKalua(count($converted_pool) - 3 * $count_high_happiness_players);
    //         } else {
    //             $families_per_player = intdiv(count($converted_pool), $count_high_happiness_players);
    //             foreach ($high_happiness_players as $player_id => $happiness) {
    //                 $this->receiveFamiliesFromPool($player_id, $families_per_player);
    //             }
    //             $this->sendFamiliesToKalua(count($converted_pool) % $count_high_happiness_players);
    //         }
    //     }

    //     // Players receive prayers (1 per 5 family, and extra if not highest)
    //     foreach ($players as $player_id => $happiness) {
    //         $family_count = $this->getFamilyCount($player_id);
    //         $prayers = intdiv($family_count, 5);
    //         if ($happiness == $happy_value_low) {
    //             $prayers += 4;
    //         } elseif ($happiness != $happy_value_high) {
    //             $prayers += 2;
    //         }
    //         // add $prayers to player prayer total
    //     }

    //     // Check for player elimination (no chief/families)
    //     foreach ($players as $player_id => $player) {
    //         if ($this->getFamilyCount($player_id) == 0 && $this->getChiefCount($player_id) == 0) {
    //             //$this->eliminatePlayer($player_id);
    //         }
    //     }

    //     // Check religions remaining
    //     if (count($this->getRemainingReligions()) == 1) {
    //         $this->gamestate->nextState('gameEnd');
    //         return;
    //     }

    //     // Change active player
    //     $this->activeNextPlayer();
    //     $this->gamestate->nextState('nextPlayer');
    // }

    // public function actPass(): void
    // {
    //     // Retrieve the active player ID.
    //     $player_id = (int)$this->getActivePlayerId();
    //     // Notify all players about the choice to pass.
    //     $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} passes'), [
    //         "player_id" => $player_id,
    //         "player_name" => $this->getActivePlayerName(),
    //     ]);
    //     // at the end of the action, move to the next state
    //     $this->gamestate->nextState("pass");
    // }

    // public function DiceRoll()
    // {
    //     // Roll the dice and update sql

    //     $dices = array();
    //     for( $i=1;$i<=5;$i++ )
    //     {
    //         $dices[$i] = bga_rand( 1,6 );
    //         self::setGameStateValue('dice'.$i, $dices[$i]);
    //         self::DbQuery("UPDATE dice SET dice_value = {$dices[$i]} WHERE dice_id = $i");
    //     }

    // }


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
    // public function stNextPlayer(): void {
    //     // Retrieve the active player ID.
    //     $player_id = (int)$this->getActivePlayerId();

    //     // Give some extra time to the active player when he completed an action
    //     $this->giveExtraTime($player_id);
        
    //     $this->activeNextPlayer();
    //     // Go to another gamestate
    //     // Here, we would detect if the game is over, and in this case use "endGame" transition instead 
    //     $this->gamestate->nextState("nextPlayer");
    // }

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

        // // Fetch the dice information from the database
        // $result["dices"] = $this->getCollectionFromDb(
        //     "SELECT `dice_id` `id`, `dice_value` `value` FROM `dice`"
        // );

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
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Tsunami->value,       'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Famine->value,        'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Floods->value,        'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::MassiveFire->value,   'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Drought->value,       'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Death->value,         'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Thunderstorm->value,  'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Revenge->value,       'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Epidemic->value,      'nbr' => 1),
            array( 'type' => CardType::GlobalDisaster->value, 'type_arg' => GlobalDisasterCard::Riots->value,         'nbr' => 1),

            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Tornado->value,        'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Earthquake->value,     'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::BadWeather->value,     'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Locust->value,         'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::TempleDestroyed->value,'nbr' => 5),
        );
        $this->disasterCards->createCards($disasterCards, 'deck');
        $this->disasterCards->shuffle('deck');

        $bonusCards = array(
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::GoodWeather->value,           'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::DoubleHarvest->value,         'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Fertility->value,             'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Festivities->value,           'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::NewLeader->value,             'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Temple->value,                'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Amulets->value,               'nbr' => 3),
        );
        $this->bonusCards->createCards($bonusCards, 'deck');
        $this->bonusCards->shuffle('deck');

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
        // {
        //     $dice = []; 
        //     $dice[] = "(1, 2, 3, 4, 5),(9, 8, 7, 6, 5)";
        // }

        // $sql = "INSERT INTO dice (dice_id, dice_value) VALUES ".implode(',', $dice);

        // TODO: Setup the initial game situation here.
        $this->setGameStateValue("roundLeader", $this->activeNextPlayer());
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

    /* Helpers */
    private function drawCard_private(string $type) : void
    {
        $card = null;
        $player_id = $this->getCurrentPlayerId();
        if ($type != STR_CARD_TYPE_DISASTER && $type != STR_CARD_TYPE_BONUS)
        {
            throw new \BgaVisibleSystemException($this->_("Unknown card type " + $type));
        }
        $this->trace( "KALUA draw a card!!" );
        if (STR_CARD_TYPE_DISASTER == $type)
        {
            $card = $this->disasterCards->pickCard( "deck", $player_id);
        }
        else if (STR_CARD_TYPE_BONUS == $type)
        {           
            $card = $this->bonusCards->pickCard( "deck", $player_id);
        }

        $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a card'), [
                'player_id' => $player_id,
                'player_name' => $this->getCurrentPlayerName(),
                'card_id' => $card['id'],
                'card_type' => $card['type'],
                'card_type_arg' => $card['type_arg']
            ]);
    }
}
