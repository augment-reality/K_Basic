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
include_once("constants.inc.php");

use BonusCard;
use CardType;
use GlobalDisasterCard;
use LocalDisasterCard;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once("constants.inc.php");
use CARD_EFFECTS;

class Game extends \Table
{
    private static array $CARD_TYPES;
    private $disasterCards;
    private $bonusCards;

    public function __construct()
    {
        parent::__construct();

        /* Global variables */
        $this->initGameStateLabels([
            "roundLeader" => 10, /* Player who leads the current round */
            "saved_state" => 11, /* Saved state for reflexive actions */
            "saved_active_player" => 12 /* Saved active player for reflexive actions */
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

    public function stActivateLeader(): void
    {
        /* No need to set the active player here */
        //$this->gamestate->setActivePlayer($this->getActivePlayerId());
        /* TODO there is a way to skip players cleanly from BGA API */
        /* Skip handling for leader (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
        $args = $this->argActivateLeader();
        if ($args['_no_notify']) 
        {
            $this->trace("KALUA skipping to next player!");
            /* Notify all players that the player is being skipped? */
            $this->gamestate->nextState();
        }
    }

    public function stNextPlayerLeader(): void
    {
        /* Update the active player to next. 
        If the active player is now the trick leader (everyone has had a chance), 
        go to PLAY ACTION CARD, otherwise ACTIVATE LEADER */
        $this->activeNextPlayer();
    
        if ($this->getActivePlayerId() == $this->getGameStateValue("roundLeader"))
        {
            $this->gamestate->nextState("phaseTwoDone");
        }
        else
        {
            $this->gamestate->nextState("checkRoundTwo");
        }
    }

    public function stNextPlayerCards(): void
    {

        /*check to see if played card was global disaster - if so go to global disaster handling state before
        continuing to check if next player is round leader*/

        //card on top might just be by item weight, not most recently played
        $last_card = $this->disasterCards->getCardOnTop('played');
        if ($last_card && (int)$last_card['type'] === CardType::GlobalDisaster->value) {
            $this->gamestate->nextState('phaseThreeCheckGlobal');
            return;
        }

        /* Update the active player to next. 
        If the active player is now the trick leader (everyone has had a chance), 
        go to PLAY ACTION CARD, otherwise ACTIVATE LEADER */
        $this->activeNextPlayer();
    
        if ($this->getActivePlayerId() == $this->getGameStateValue("roundLeader"))
        {
            $this->gamestate->nextState("resolveCards");
        }
        else
        {
            $this->gamestate->nextState("checkRoundThree");
        }
    }

    public function stPlayCard(): void
    {

        /* skip handling for play card (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
    }

    public function stGlobalOption(): void
    {
        //actions for global disaster card options will send to play again

        if ($this->getActivePlayerId() != $this->getGameStateValue("roundLeader")) {
            $this->gamestate->nextState("checkRoundThree");
        }
            

        /* skip handling when none to play (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
        //$this->gamestate->nextState("convert"); // for now, just skip
    }

    public function stResolveCard(): void
    {
        /* Check if there is a card currently resolving (we come back to this state from others while a card is still resolving) - if so continue to resolve that card
            Else if there are cards remaining move the next available card to resolving
            Else we’re done resolving cards - set the active player to the trick leader and go to PHASE THREE PLAY ACTION CARD
            Resolve (or continue resolving) the resolving card */

        $this->gamestate->nextState('noCards');

    }
    
    public function stSelectTarget(): void
    {
        /* Active player must select the player to target with their disaster */
    }

    public function stConvertPray(): void
    {

        $this->notifyAllPlayers(
            'roundEnded',
            clienttranslate('Round has ended. Unhappy religions lose families and all religions pray.'),
            []
        );

    // Update happiness, prayer, families based on end of round rules
    // Check for end game condition
    // Check that they haven’t been completely eliminated - cycle until we found someone who hasn’t 

        // Initialize constants
        $happinessScores = [];
        $converted_pool = 0;

        // Get existing family and prayer counts for all players
        $previous_family = [];
        $previous_prayer = [];
        
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $_) {
            $previous_family[$player_id] = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $player_id");
            $previous_prayer[$player_id] = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        }

        // Collect happiness scores
        foreach ($players as $player_id => $_) {
            $happinessScores[$player_id] = (int)$this->getUniqueValueFromDB("SELECT player_happiness FROM player WHERE player_id = $player_id");
        }

        // Find lowest and highest happiness scores
        $happy_value_low = min($happinessScores);
        $happy_value_high = max($happinessScores);

        // Get player IDs with highest happiness score
        $high_happiness_players = array_keys(array_filter($happinessScores, function($happiness) use ($happy_value_high) {
            return $happiness == $happy_value_high;
        }));
        
        // Count of players with highest happiness
        $high_players = count($high_happiness_players);



        // Redistribute families, unless everyone has same happiness
        if ($happy_value_low != $happy_value_high) {

            // Collect 2 families from low and 1 from middle happiness players
            foreach ($players as $player_id => $happiness) {
                if ($happiness == $happy_value_low) {
                    $player_family = $this->getFamilyCount($player_id);
                    $to_convert = min(2, $player_family);
                    if ($to_convert > 0) {
                        $this->setFamilyCount($player_id, $player_family - $to_convert);
                        $converted_pool += $to_convert;
                    }
                } elseif ($happiness != $happy_value_high) {
                    $player_family = $this->getFamilyCount($player_id);
                    if ($player_family > 0) {
                        $this->setFamilyCount($player_id, $player_family - 1);
                        $converted_pool += 1;
                    }
                }
            }

            // Divide converted_pool among high happiness players, remainder to atheist families
            $fams_to_happy = intdiv($converted_pool, $high_players);
            $remainder = $converted_pool % $high_players;
            foreach ($high_happiness_players as $player_id) {
                $this->getFromPool($player_id, $fams_to_happy);
            }

            // Move remainder to atheist families (global_id = 101)
            if ($remainder > 0) {
                $this->DbQuery("UPDATE global SET global_value = global_value + $remainder WHERE global_id = 101");
            }
        }

        // Players receive prayers (1 per 5 family, and extra if not highest)
        foreach ($players as $player_id => $_) {
            $family_count = $this->getFamilyCount($player_id);
            $prayers = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
            $prayers += floor($family_count / 5);
            if ($happinessScores[$player_id] == $happy_value_low) {
                $prayers += 4;
            } elseif ($happinessScores[$player_id] != $happy_value_high) {
                $prayers += 2;
            }
            self::DbQuery("UPDATE player SET player_prayer = $prayers WHERE player_id = $player_id");
        }

        // Check for player elimination (no chief/families)
        foreach ($players as $player_id => $player) {
            if ($this->getFamilyCount($player_id) == 0 && $this->getChiefCount($player_id) == 0) {
                self::DbQuery("UPDATE player SET player_eliminated = 1 WHERE player_id = $player_id");
            }
        }

        // Check religions remaining and proceed to end game if only one or zero remain
        $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
        $player_count = count($players);
        if ($eliminated_count > $player_count - 1) {
            $this->gamestate->nextState('gameEnd');
            return;
        }

        // Update round leader to next non-eliminated player
        $next_leader = $this->getGameStateValue("roundLeader");
        while ((int)$this->getUniqueValueFromDb("SELECT player_eliminated FROM player WHERE player_id = $next_leader") == 1) {
            $next_leader = $this->activeNextPlayer();
        }


        // Notify who was the happiest and most unhappy
        $happiest_names = array_map(function($pid) { return $this->getPlayerNameById($pid); }, $high_happiness_players);
        $unhappy_players = array_keys(array_filter($happinessScores, function($happiness) use ($happy_value_low) {
            return $happiness == $happy_value_low;
        }));
        $unhappy_names = array_map(function($pid) { return $this->getPlayerNameById($pid); }, $unhappy_players);

        $this->notifyAllPlayers('happinessReport', clienttranslate('Happiest: ${happiest}, Most unhappy: ${unhappy}'), [
            'happiest' => implode(', ', $happiest_names),
            'unhappy' => implode(', ', $unhappy_names)
        ]);
        
        // Notify all players of family changes for each player
        foreach ($players as $player_id => $_) {

            $family_count = $this->getFamilyCount($player_id);
            $eliminated = (int)$this->getUniqueValueFromDb("SELECT player_eliminated FROM player WHERE player_id = $player_id");
            $happiness = $happinessScores[$player_id];
            $prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");

            // Compute deltas (difference from previous round)
            $family_delta = $family_count - $previous_family[$player_id];
            $prayer_delta = $prayer - $previous_prayer[$player_id];

            $family_change = $family_delta >= 0 ? "increased" : "decreased";
            $prayer_change = $prayer_delta >= 0 ? "increased" : "decreased";

            $this->notifyAllPlayers(
                'playerCountsChanged',
                clienttranslate('${player_name}: Families ${family_count} (${family_change} by ${family_delta}), Prayers ${prayer} (${prayer_change} by ${prayer_delta})'),
                [
                    'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id),
                    'family_count' => $family_count,
                    'family_change' => $family_change,
                    'family_delta' => abs($family_delta),
                    'prayer' => $prayer,
                    'prayer_change' => $prayer_change,
                    'prayer_delta' => abs($prayer_delta),
                    'eliminated' => $eliminated,
                    'happiness' => $happiness
                ]
            );

            if ($eliminated == 1) {
            $this->notifyAllPlayers('playerEliminated', clienttranslate('${player_name} has been eliminated!'), [
                'player_id' => $player_id,
                'player_name' => $this->getPlayerNameById($player_id)
            ]);
            }
        }


        $this->setGameStateValue("roundLeader", $next_leader);
        $this->gamestate->nextState('phaseOneDraw');
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
            == HAND_SIZE )
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
            >= HAND_SIZE)
        {
            $this->gamestate->nextState('FreeAction');
        }
    }

    /***** Leader state actions *****/
    public function actGiveSpeech(): void
{
        $this->trace("KALUA gives a speech!");
        $player_id = $this->getCurrentPlayerId();

        self::DbQuery( "UPDATE player SET player_happiness = player_happiness + 1 WHERE player_id = {$this->getActivePlayerId()}");

        // Notify all players
        $this->notifyAllPlayers('giveSpeech', clienttranslate('${player_name} gave a speech'), [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName()
        ]);

        $this->gamestate->nextState('nextPlayer');
    }

    public function actConvertAtheists(): void
    {
        $this->trace("KALUA convert atheists!");
        $player_id = $this->getCurrentPlayerId();

        // Get current number of atheist families
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        // Move up to two atheist families to the player, if they exist
        $toConvert = min(2, $atheistCount);
        if ($toConvert > 0) {
            self::DbQuery("UPDATE global SET global_value = global_value - $toConvert WHERE global_id = 101");
            self::DbQuery("UPDATE player SET player_family = player_family + $toConvert WHERE player_id = {$player_id}");
        }

        $this->notifyAllPlayers('convertAtheists', clienttranslate('${player_name} converted ${num_atheists} atheist(s)'), [
                'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName(),
                'num_atheists' => $toConvert
            ]);

        $this->gamestate->nextState('nextPlayer');
    }

    public function actConvertBelievers(int $target_player_id): void
    {
        $this->trace("KALUA convert believers!");
        // Move one family from target_player_id to current player
        $current_player_id = $this->getCurrentPlayerId();

        // Get family counts
        $target_family = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $target_player_id");
        $current_family = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $current_player_id");

        // Only transfer if target has at least one family
        if ($target_family > 0) {
            $this->trace("KALUA at least one family!");
            self::DbQuery("UPDATE player SET player_family = player_family - 1 WHERE player_id = $target_player_id");
            self::DbQuery("UPDATE player SET player_family = player_family + 1 WHERE player_id = $current_player_id");

            $this->notifyAllPlayers('convertBelievers', clienttranslate('${player_name} converted a believer from ${target_name}'), [
                'player_id' => $current_player_id,
                'player_name' => $this->getActivePlayerName(),
                'target_id' => $target_player_id,
                'target_name' => $this->getPlayerNameById($target_player_id)
            ]);
        }
        else{
            $this->trace("KALUA not converting any families!");
        }

        $this->gamestate->nextState('nextPlayer');
    }

    public function actSacrificeLeader(): void
    {
        /* Increase player's happiness by one */
        $this->trace("KALUA sacrifices leader!");

        $player_id = $this->getCurrentPlayerId();

        /* Remove the player's leader */
        self::DbQuery( "UPDATE player SET player_chief=0 WHERE player_id = {$player_id}");

        /* Convert up to 5 atheist families */
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        $toConvert = min(5, $atheistCount);
        if ($toConvert > 0) {
            self::DbQuery("UPDATE global SET global_value = global_value - $toConvert WHERE global_id = 101");
            self::DbQuery("UPDATE player SET player_family = player_family + $toConvert WHERE player_id = {$player_id}");
        }

        $this->notifyAllPlayers('sacrificeLeader', clienttranslate('${player_name}\'s leader gave a massive speeach
                                    and sacrificed themself, converting ${num_atheists} atheists'), [
                'player_id' => $player_id,
                'player_no' => $this->getPlayerNoById($player_id),
                'player_name' => $this->getActivePlayerName(),
                'num_atheists' => $toConvert
            ]);
        
        $this->gamestate->nextState('nextPlayer');
    }


    /***************************************/

    /***** Play card actions ******/
    public function actPlayCard(int $card_id): void
    {
        // 1. Check if action is allowed
        $this->checkAction('actPlayCard');

        // 2. Get current player (cast to int since moveCard expects int)
        $player_id = (int)$this->getActivePlayerId();

        // 3. Validate the card belongs to the player
        $card = $this->getCard($card_id);
        if ($card === null) {
            throw new \BgaUserException("Card not found");
        }
        if ($card['location'] !== 'hand' || $card['location_arg'] != $player_id) {
            throw new \BgaUserException("This card is not in your hand");
        }

        // 4. Get prayer cost before checking if card can be played
        $prayer_cost = $this->getCardPrayerCost($card);

        // 5. Apply game rules validation here
        // Check if this card can be played according to Kalua rules (prayer cost)
        if (!$this->canPlayCard($player_id, $card)) {
            $card_name = $this->getCardName($card);
            throw new \BgaUserException("You don't have enough prayer points to play $card_name");
        }

        // 6. Move card to played location
        $this->moveCard($card_id, 'played', $player_id);

        // 7. Get updated player stats for notifications
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        $disaster_cards_in_hand = $this->disasterCards->countCardInLocation("hand", $player_id);
        $bonus_cards_in_hand = $this->bonusCards->countCardInLocation("hand", $player_id);
        $total_cards_in_hand = $disaster_cards_in_hand + $bonus_cards_in_hand;

        // 8. Send notification to all players (include card_type and card_type_arg for frontend)
        $this->notifyAllPlayers('cardPlayed',
            clienttranslate('${player_name} plays ${card_name}'), [
                'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName(),
                'card_id' => $card_id,
                'card_name' => $this->getCardName($card),
                'card_type' => $card['type'],
                'card_type_arg' => $card['type_arg']
            ]);

        // 9. If prayer was spent, notify about prayer cost and updated sidebar counters
        if ($prayer_cost > 0) {
            $this->notifyAllPlayers('prayerSpent', 
                clienttranslate('${player_name} spends ${prayer_cost} prayer points to play ${card_name}'), [
                    'player_id' => $player_id,
                    'player_name' => $this->getActivePlayerName(),
                    'prayer_cost' => $prayer_cost,
                    'new_prayer_total' => $player_prayer,
                    'card_name' => $this->getCardName($card)
                ]);
        }

        // 10. Notify about updated card count in sidebar
        $this->notifyAllPlayers('playerStatsUpdated',
            clienttranslate('${player_name} now has ${card_count} cards'), [
                'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName(),
                'card_count' => $total_cards_in_hand,
                'prayer_points' => $player_prayer
            ]);

        if ((int)$card['type'] === CardType::GlobalDisaster->value) {
            $this->gamestate->nextState('phaseThreeCheckGlobal');
            return;
        } else {
            $this->gamestate->nextState('nextPlayerThree');
            return;
        }
    }

    public function actPlayCardPass(): void
    {
        $this->trace("KALUA passes their turn.");
        $this->gamestate->nextState('nextPlayerThree');
    }

    public function actSayConvert(): void
    {
        $this->gamestate->nextState("resolveCards");
    }

    /***** Reflexive actions - can be taken anytime *****/
    
    /**
     * Action to enter the reflexive buy card state
     */
    public function actGoToBuyCardReflex(): void
    {
        $this->checkAction('actGoToBuyCardReflex');
        
        // Check if player has enough prayer points
        $player_id = $this->getCurrentPlayerId();
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        
        if ($player_prayer < 5) {
            throw new \BgaUserException("You need at least 5 prayer points to buy a card");
        }
        
        // Save the current state to return to later
        $this->setGameStateValue('saved_state', $this->gamestate->state_id());
        $this->setGameStateValue('saved_active_player', $player_id);
        
        $this->gamestate->nextState('buyCardReflex');
    }

    /**
     * Draw a card anytime by spending 5 prayer points
     */
    public function actDrawCardAnytime(string $type): void
    {
        $this->checkAction('actDrawCardAnytime');
        
        // Validate card type
        if ($type !== STR_CARD_TYPE_DISASTER && $type !== STR_CARD_TYPE_BONUS) {
            throw new \BgaUserException("Invalid card type: $type");
        }
        
        $player_id = $this->getCurrentPlayerId();
        
        // Check if player has enough prayer points
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        if ($player_prayer < 5) {
            throw new \BgaUserException("You need 5 prayer points to buy a card");
        }
        
        // Deduct 5 prayer points
        $this->DbQuery("UPDATE player SET player_prayer = player_prayer - 5 WHERE player_id = $player_id");
        
        // Draw the card using the existing private function, passing player_id explicitly
        $this->drawCard_private($type, $player_id);
        
        // Update player card count in the database
        $this->DbQuery("UPDATE player SET player_card_count = player_card_count + 1 WHERE player_id = $player_id");
        
        // Notify about prayer point cost
        $this->notifyAllPlayers('prayerSpent', 
            clienttranslate('${player_name} spends 5 prayer points to buy a card'), [
                'player_id' => $player_id,
                'player_name' => $this->getCurrentPlayerName(),
                'prayer_spent' => 5,
                'new_prayer_total' => $player_prayer - 5
            ]);
        
        // Return to previous state
        $this->returnFromReflexiveState();
    }

    /**
     * Cancel buying a card and return to previous state
     */
    public function actCancelBuyCard(): void
    {
        $this->checkAction('actCancelBuyCard');
        $this->returnFromReflexiveState();
    }

    /**
     * Helper function to return from reflexive state to the saved state
     */
    private function returnFromReflexiveState(): void
    {
        $saved_state = $this->getGameStateValue('saved_state');
        $saved_player = $this->getGameStateValue('saved_active_player');
        
        // Clear saved values
        $this->setGameStateValue('saved_state', 0);
        $this->setGameStateValue('saved_active_player', 0);
        
        // Return to the saved state - jumpToState should restore the context properly
        $this->gamestate->jumpToState($saved_state);
        
        // Log the restoration for debugging
        $this->trace("Returned from reflexive state to state $saved_state for player $saved_player");
    }

    public function actAvoidGlobal(): void
    {
        //implement logic to flag player as avoiding global disaster or something

        // Transition to next state
        $this->gamestate->nextState("playAgain");
    }

    public function actDoubleGlobal(): void
    {
        //implement logic to flag double global disaster (play card twice?)
        //don't check for elimnination until end of global disaster resolution

        $this->gamestate->nextState("playAgain");
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

    /***** helpers ******/

    /**
     * Get a card from the appropriate deck based on card ID
     * @param int $card_id The card ID to retrieve
     * @return array|null The card data or null if not found
     */
    public function getCard(int $card_id): ?array
    {
        // Try to get the card from disaster deck first
        $card = $this->disasterCards->getCard($card_id);
        if ($card !== null) {
            return $card;
        }
        
        // If not found in disaster deck, try bonus deck
        $card = $this->bonusCards->getCard($card_id);
        if ($card !== null) {
            return $card;
        }
        
        // Card not found in either deck
        return null;
    }

    /**
     * Move a card from the appropriate deck to a new location
     * @param int $card_id The card ID to move
     * @param string $location The target location
     * @param int $location_arg The location argument (usually player ID)
     * @return bool Success of the operation
     */
    public function moveCard(int $card_id, string $location, int $location_arg = 0): bool
    {
        // Try to move from disaster deck first
        $card = $this->disasterCards->getCard($card_id);
        if ($card !== null) {
            $this->disasterCards->moveCard($card_id, $location, $location_arg);
            return true;
        }
        
        // If not found in disaster deck, try bonus deck
        $card = $this->bonusCards->getCard($card_id);
        if ($card !== null) {
            $this->bonusCards->moveCard($card_id, $location, $location_arg);
            return true;
        }
        
        // Card not found in either deck
        return false;
    }

    /**
     * Get card name based on card data
     * @param array $card Card data array with 'type' and 'type_arg' fields
     * @return string The human-readable card name
     */
    public function getCardName(array $card): string
    {
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];

        switch ($card_type) {
            case CardType::GlobalDisaster->value:
                return match ($card_type_arg) {
                    GlobalDisasterCard::Tsunami->value => clienttranslate('Tsunami'),
                    GlobalDisasterCard::Famine->value => clienttranslate('Famine'),
                    GlobalDisasterCard::Floods->value => clienttranslate('Floods'),
                    GlobalDisasterCard::MassiveFire->value => clienttranslate('Massive Fire'),
                    GlobalDisasterCard::Drought->value => clienttranslate('Drought'),
                    GlobalDisasterCard::Death->value => clienttranslate('Death'),
                    GlobalDisasterCard::Thunderstorm->value => clienttranslate('Thunderstorm'),
                    GlobalDisasterCard::Revenge->value => clienttranslate('Revenge'),
                    GlobalDisasterCard::Epidemic->value => clienttranslate('Epidemic'),
                    GlobalDisasterCard::Riots->value => clienttranslate('Riots'),
                    default => clienttranslate('Unknown Global Disaster'),
                };

            case CardType::LocalDisaster->value:
                return match ($card_type_arg) {
                    LocalDisasterCard::Tornado->value => clienttranslate('Tornado'),
                    LocalDisasterCard::Earthquake->value => clienttranslate('Earthquake'),
                    LocalDisasterCard::BadWeather->value => clienttranslate('Bad Weather'),
                    LocalDisasterCard::Locust->value => clienttranslate('Locust'),
                    LocalDisasterCard::TempleDestroyed->value => clienttranslate('Temple Destroyed'),
                    default => clienttranslate('Unknown Local Disaster'),
                };

            case CardType::Bonus->value:
                return match ($card_type_arg) {
                    BonusCard::GoodWeather->value => clienttranslate('Good Weather'),
                    BonusCard::DoubleHarvest->value => clienttranslate('Double Harvest'),
                    BonusCard::Fertility->value => clienttranslate('Fertility'),
                    BonusCard::Festivities->value => clienttranslate('Festivities'),
                    BonusCard::NewLeader->value => clienttranslate('New Leader'),
                    BonusCard::Temple->value => clienttranslate('Temple'),
                    BonusCard::Amulets->value => clienttranslate('Amulets'),
                    default => clienttranslate('Unknown Bonus'),
                };

            default:
                return clienttranslate('Unknown Card');
        }
    }



    /**
     * Get card type name (category) based on card data
     * @param array $card Card data array with 'type' field
     * @return string The card type name
     */
    public function getCardTypeName(array $card): string
    {
        $card_type = (int)$card['type'];

        return match ($card_type) {
            CardType::GlobalDisaster->value => clienttranslate('Global Disaster'),
            CardType::LocalDisaster->value => clienttranslate('Local Disaster'),
            CardType::Bonus->value => clienttranslate('Bonus'),
            default => clienttranslate('Unknown Type'),
        };
    }

    // Aux function to move families from pool to player in convert/pray phase
    public function getFromPool($player_id, $num_families) {
        // Move families from global pool (atheists) to player
        self::DbQuery("UPDATE player SET player_family = player_family + $num_families WHERE player_id = $player_id");
    }

    // Aux function to count families in convert/pray phase
    public function getFamilyCount($player_id) {
        return (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = {$player_id}");
    }

    // Aux function to set families for a player
    public function setFamilyCount($player_id, $count) {
        self::DbQuery("UPDATE player SET player_family = $count WHERE player_id = $player_id");
    }

    // Aux function to count chiefs for a player
    public function getChiefCount($player_id) {
        return (int)$this->getUniqueValueFromDb("SELECT player_chief FROM player WHERE player_id = {$player_id}");
    }

    public function check_playerHasLeader() : bool
    {
        $leader_int = (int)$this->getUniqueValueFromDb("SELECT player_chief FROM player WHERE player_id = {$this->getActivePlayerId()}");
        $this->trace(sprintf("KALUA leader int: %d", $leader_int));
        return $leader_int == 1;
    }

    /**
     * Check if a card can be played by a player based on their prayer points
     * @param int $player_id The player attempting to play the card
     * @param array|null $card The card data array with 'type' and 'type_arg' fields
     * @return bool True if the card can be played, false otherwise
     */
    /**
     * Get the prayer cost for a specific card
     * @param array $card Card data array with 'type' and 'type_arg' fields
     * @return int The prayer cost for this card
     */
    public function getCardPrayerCost(array $card): int
    {
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        
        // Define card effects (same as in canPlayCard)
        $CARD_EFFECTS = [
            CardType::GlobalDisaster->value => [
                GlobalDisasterCard::Tsunami->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Famine->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Floods->value => ['prayer_cost' => 0],
                GlobalDisasterCard::MassiveFire->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Drought->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Death->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Thunderstorm->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Revenge->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Epidemic->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Riots->value => ['prayer_cost' => 0],
            ],
            CardType::LocalDisaster->value => [
                LocalDisasterCard::Tornado->value => ['prayer_cost' => 4],
                LocalDisasterCard::Earthquake->value => ['prayer_cost' => 5],
                LocalDisasterCard::BadWeather->value => ['prayer_cost' => 1],
                LocalDisasterCard::Locust->value => ['prayer_cost' => 3],
                LocalDisasterCard::TempleDestroyed->value => ['prayer_cost' => 5],
            ],
            CardType::Bonus->value => [
                BonusCard::GoodWeather->value => ['prayer_cost' => 2],
                BonusCard::DoubleHarvest->value => ['prayer_cost' => 5],
                BonusCard::Fertility->value => ['prayer_cost' => 6],
                BonusCard::Festivities->value => ['prayer_cost' => 5],
                BonusCard::NewLeader->value => ['prayer_cost' => 5],
                BonusCard::Temple->value => ['prayer_cost' => 5],
                BonusCard::Amulets->value => ['prayer_cost' => 4],
            ]
        ];
        
        if (isset($CARD_EFFECTS[$card_type][$card_type_arg]['prayer_cost'])) {
            return (int)$CARD_EFFECTS[$card_type][$card_type_arg]['prayer_cost'];
        }
        
        return 0; // Default to 0 if not found
    }

    public function canPlayCard(int $player_id, ?array $card): bool
    {
        
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");

        // Define card effects directly in the function to avoid global variable issues
        $CARD_EFFECTS = [
            CardType::GlobalDisaster->value => [
                GlobalDisasterCard::Tsunami->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Famine->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Floods->value => ['prayer_cost' => 0],
                GlobalDisasterCard::MassiveFire->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Drought->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Death->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Thunderstorm->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Revenge->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Epidemic->value => ['prayer_cost' => 0],
                GlobalDisasterCard::Riots->value => ['prayer_cost' => 0],
            ],
            CardType::LocalDisaster->value => [
                LocalDisasterCard::Tornado->value => ['prayer_cost' => 1],
                LocalDisasterCard::Earthquake->value => ['prayer_cost' => 2],
                LocalDisasterCard::BadWeather->value => ['prayer_cost' => 1],
                LocalDisasterCard::Locust->value => ['prayer_cost' => 1],
                LocalDisasterCard::TempleDestroyed->value => ['prayer_cost' => 5],
            ],
            CardType::Bonus->value => [
                BonusCard::GoodWeather->value => ['prayer_cost' => 2],
                BonusCard::DoubleHarvest->value => ['prayer_cost' => 3],
                BonusCard::Fertility->value => ['prayer_cost' => 4],
                BonusCard::Festivities->value => ['prayer_cost' => 1],
                BonusCard::NewLeader->value => ['prayer_cost' => 5],
                BonusCard::Temple->value => ['prayer_cost' => 4],
                BonusCard::Amulets->value => ['prayer_cost' => 3],
            ]
        ];
        
        $prayer_cost = (int)$CARD_EFFECTS[$card_type][$card_type_arg]['prayer_cost'];
        
        // Check if player has enough prayer points
        $can_play = $player_prayer >= $prayer_cost;

        if ($can_play && $prayer_cost > 0) {
            $new_prayer = $player_prayer - $prayer_cost;
            $this->DbQuery("UPDATE player SET player_prayer = $new_prayer WHERE player_id = $player_id");
        }      

        return $can_play;
    }


    /******* Arg functions ************/
    public function argActivateLeader() : array
    {
        return [
            '_no_notify' => !$this->check_playerHasLeader(),
        ];
    }

    public function argPlayCard() : array
    {
        $this->trace("KALUA getting play card args");
        return ['_private' => ['active' => [
            'playableCards' => $this->checkPlayableCards($this->getActivePlayerId()),
            'isRoundLeader' => $this->checkIsRoundLeader($this->getActivePlayerId())
        ]]];
    }
    


    // public function stGameSetup(): void
    // {
    //     // Wait for each player to select five cards
    //     $players = $this->loadPlayersBasicInfos();

    //     // Proceed to the next game state
    //     $this->gamestate->nextState("Initial_Draw");
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
    protected function getAllDatas(): array
    {
        $result = [];
        $current_player_id = (int) $this->getCurrentPlayerId();
        $result["players"] = self::getCollectionFromDb(
            "SELECT player_id id, 
                player_no sprite, 
                player_family family, 
                player_chief chief, 
                player_happiness happiness,
                player_prayer prayer,
                player_temple temple,
                player_amulet amulet,
                player_card_count cards
                FROM player"
        );
        /* add name and card type counts */
        foreach ($result["players"] as &$player)
        {
            $player["name"] = $this->getPlayerNameById($player["id"]);
            
            // Add disaster and bonus card counts for all players (visible information)
            $player["disaster_cards"] = $this->disasterCards->countCardInLocation("hand", $player["id"]);
            $player["bonus_cards"] = $this->bonusCards->countCardInLocation("hand", $player["id"]);
        }

        // Fetch the number of atheist families from the database
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        $result["atheist_families"] = $atheistCount;

        // Add round leader information
        $result["round_leader"] = $this->getGameStateValue("roundLeader");

        // // Fetch the dice information from the database
        // $result["dices"] = $this->getCollectionFromDb(
        //     "SELECT `dice_id` `id`, `dice_value` `value` FROM `dice`"
        // );

        /* Get all cards this player has and where it is */
        $result["handDisaster"] = $this->disasterCards->getPlayerHand($current_player_id);
        $result["handBonus"] = $this->bonusCards->getPlayerHand($current_player_id);

        /* TODO get size of each players hand */

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
 
        $gameinfos = $this->getGameinfos();

        // Set the colors of the players - should match player tokens
        $player_color_list = ["#4685FF", "#2EA232", "#C22D2D", "#C8CA25","#913CB3"];
        $default_colors = [...$player_color_list];

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

        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

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
            self::DbQuery("UPDATE player SET player_family=5, player_chief=1, player_happiness=5 WHERE player_id=$player_id");
        }

        // Initialize atheist families (e.g., 3 per player, stored in a global table or variable)
        $atheist_start = count($players) * 3;
        $this->DbQuery("INSERT INTO global (global_id, global_value) VALUES (101, $atheist_start)");

        // Init game statistics.

        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);

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
    private function drawCard_private(string $type, int $player_id = null) : void
    {
        $card = null;
        if ($player_id === null) {
            $player_id = $this->getCurrentPlayerId();
        }
        
        if ($type != STR_CARD_TYPE_DISASTER && $type != STR_CARD_TYPE_BONUS)
        {
            throw new \BgaVisibleSystemException($this->_("Unknown card type " + $type));
        }
        $this->trace( "KALUA draw a card!! Player: $player_id, Type: $type" );
        
        // Check deck size before drawing
        if (STR_CARD_TYPE_DISASTER == $type)
        {
            $deck_count = $this->disasterCards->countCardInLocation("deck");
            $this->trace("Disaster deck has $deck_count cards");
            if ($deck_count == 0) {
                throw new \BgaUserException("No more disaster cards available");
            }
            $card = $this->disasterCards->pickCardForLocation("deck", "hand", $player_id);
        }
        else if (STR_CARD_TYPE_BONUS == $type)
        {           
            $deck_count = $this->bonusCards->countCardInLocation("deck");
            $this->trace("Bonus deck has $deck_count cards");
            if ($deck_count == 0) {
                throw new \BgaUserException("No more bonus cards available");
            }
            $card = $this->bonusCards->pickCardForLocation("deck", "hand", $player_id);
        }

        if ($card === null) {
            throw new \BgaUserException("No more cards available in the $type deck");
        }

        $this->trace("Card drawn: " . json_encode($card));

        // Public notification that a card was drawn
        $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a card'), [
                'player_id' => $player_id,
                'player_name' => $this->getPlayerNameById($player_id),
                'card_id' => $card['id'],
                'card_type' => $card['type'],
                'card_type_arg' => $card['type_arg']
            ]);

        // Private notification to the player with card details
        $this->notifyPlayer($player_id, 'cardDrawn', '', [
            'card' => $card
        ]);
    }

    function checkPlayableCards($player_id): array {
        /* A card is playable if the player has the prayer points to play it */
        $available_points = (int)$this->getUniqueValueFromDB("SELECT player_prayer FROM player WHERE player_id = $player_id");
        $cardsInHand_disaster = $this->disasterCards->getPlayerHand($player_id);
        $cardsInHand_bonus = $this->bonusCards->getPlayerHand($player_id);

        $this->trace(sprintf("KALUA points available: %d", $available_points));

        $playableCardIds = [];

        foreach ($cardsInHand_disaster as $card)
        {
            $type = $card['type'];
            $type_arg = $card['type_arg'];
            $cost = CARD_EFFECTS[$type][$type_arg]['prayer_cost'];

            $this->trace(sprintf("KALUA card type %d arg %d cost disaster: %d", $type, $type_arg, $cost));
            if ($cost <= $available_points)
            {
                $playableCardIds[] = ['id' => (int)$card['id'], 'type' => (int)$card['type'], 'type_arg' => (int)$card['type_arg']];
                $insterted_card = end($playableCardIds);
                $this->trace(sprintf("inserted id: %d, type: %d, type_arg: %d", $insterted_card["id"], $insterted_card["type"], $insterted_card["type_arg"]));
            }
        }

        foreach ($cardsInHand_bonus as $card)
        {
            $type = $card['type'];
            $type_arg = $card['type_arg'];
            $cost = CARD_EFFECTS[$type][$type_arg]['prayer_cost'];
            $this->trace(sprintf("KALUA card type %d arg %d cost disaster: %d", $type, $type_arg, $cost));
            if ($cost <= $available_points)
            {
                $playableCardIds[] = ['id' => (int)$card['id'], 'type' => (int)$card['type'], 'type_arg' => (int)$card['type_arg']];
                $insterted_card = end($playableCardIds);
                $this->trace(sprintf("inserted id: %d, type: %d, type_arg: %d", $insterted_card["id"], $insterted_card["type"], $insterted_card["type_arg"]));
            }
        }

        $this->trace(serialize($playableCardIds));
        return $playableCardIds;
    }

    function checkIsRoundLeader($player_id): bool {
        return $player_id == $this->getGameStateValue("roundLeader");
    }
}
