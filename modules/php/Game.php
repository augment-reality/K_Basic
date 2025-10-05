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
require_once("constants.inc.php");

class Game extends \Table
{
    private static array $CARD_TYPES;
    private $disasterCards;
    private $bonusCards;
    private array $playerUsedAmulet = []; // Track which players used amulets in current resolution
    private array $diceResults = []; // Track dice results for current resolution
    private bool $amuletsResolved = false; // Track if amulets have been resolved for current card
    private array $multiactiveAmuletPlayers = []; // Track which players are multiactive for amulet decisions

    // Global disaster choice costs
    const GLOBAL_DISASTER_AVOID_COST = 6;
    const GLOBAL_DISASTER_DOUBLE_COST = 12;

    public function __construct()
    {
        parent::__construct();

        /* Global variables */
        $this->initGameStateLabels([
            "roundLeader" => 10, /* Player who leads the current round */
            "saved_state" => 11, /* Saved state for reflexive actions */
            "saved_active_player" => 12, /* Saved active player for reflexive actions */
            "current_global_disaster" => 13, /* Card ID of the current global disaster being processed */
            "round_leader_played_card" => 14, /* Whether round leader has played a card this turn */
            "round_leader_continuing_play" => 15, /* Whether round leader is continuing to play multiple cards */
            "discard_completed_for_card" => 16, /* ID of the card for which discard has been completed */
            "dice_completed_for_card" => 17, /* ID of the card for which dice rolling has been completed */
            "convert_pray_requested" => 18, /* Whether the round leader requested convert/pray phase */
            "amulet_completed_for_card" => 19, /* ID of the card for which amulet resolution has been completed */
            "round_leader_passed_this_cycle" => 20 /* Whether round leader has passed in this cycle */
        ]);          

        //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards->init( "bonus_card" );
        
    }


////////////Game State Actions /////////////////////

    public function stQuickDraw(): void
    {
        // Check if quickstart cards option is enabled
        $quickstart_enabled = $this->tableOptions->get(100) == 2;
        
        if ($quickstart_enabled) {
            // Auto-deal quickstart cards to all players
            $players = $this->getCollectionFromDb("SELECT player_id FROM player ORDER BY player_no");
            
            foreach($players as $player) {
                $player_id = (int)$player['player_id']; // Cast to integer
                
                // Draw 3 disaster cards for each player
                for ($i = 0; $i < 3; $i++) {
                    $this->drawCard_private(STR_CARD_TYPE_DISASTER, $player_id, true);
                }
                
                // Draw 2 bonus cards for each player
                for ($i = 0; $i < 2; $i++) {
                    $this->drawCard_private(STR_CARD_TYPE_BONUS, $player_id, true);
                }
            }
            
            // Notify all players about the quickstart setup
            $this->notifyAllPlayers('quickstartCardsDealt', clienttranslate('Quickstart: Each player has been dealt 3 disaster cards and 2 bonus cards'), [
                'players' => array_keys($players)
            ]);
            
            // Skip ahead to the drawToFive phase (Phase One Draw)
            $this->gamestate->nextState('drawToFive');
        } else {
            // Route to normal initial draw where players choose their own cards
            $this->gamestate->setAllPlayersMultiactive();
            $this->gamestate->nextState('normalDraw');
        }
    }

    public function stInitialDraw(): void
    {
        // Normal game: players choose their own cards
        $this->gamestate->setAllPlayersMultiactive();
    }

    public function stInitialFinish(): void
    {
        /* State to do any necessary cleanup, 
        notifications to UI (e.g. cards drawn by other players) 
        and set the active player to the first player */

        // Send summary notification of all players' initial card draws
        $playerSummary = [];
        $players = $this->getCollectionFromDb("SELECT player_id, player_name FROM player ORDER BY player_no");
        
        foreach ($players as $player) {
            $player_id = $player['player_id'];
            $disasterCount = $this->disasterCards->countCardInLocation("hand", $player_id);
            $bonusCount = $this->bonusCards->countCardInLocation("hand", $player_id);
            
            $playerSummary[] = [
                'player_id' => $player_id,
                'player_name' => $player['player_name'],
                'disaster_cards' => $disasterCount,
                'bonus_cards' => $bonusCount,
                'total_cards' => $disasterCount + $bonusCount
            ];
        }
        
        $this->notifyAllPlayers('initialDrawComplete', clienttranslate('All players have completed their initial card selection'), [
            'players' => $playerSummary
        ]);

        /* Active player is already set */
        //$this->activeNextPlayer();
        $this->gamestate->nextState();
    }

    public function stPhaseOneDraw(): void
    {
        // State action for phaseOneDraw
        // This is called when entering the draw phase
        // No special setup needed - player can start drawing cards
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
            /* Notify all players that the player is being skipped? */
            $this->gamestate->nextState();
        }
    }

    public function stNextPlayerLeader(): void
    {
        /* Update the active player to next. 
        If the active player is now the trick leader (everyone has had a chance), 
        go to PLAY ACTION CARD, otherwise ACTIVATE LEADER */
        
        // Check if only one player remains (all others eliminated)
        $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
        $total_players = $this->getPlayersNumber();
        
        if ($eliminated_count >= $total_players - 1) {
            // Only one player remains, skip to next phase directly
            $this->gamestate->nextState("phaseTwoDone");
            return;
        }
        
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
        /* Update the active player to next. 
        When the cycle returns to the round leader (after they passed and others played), 
        resolve cards first. Otherwise continue normal play. */
        
        // Check if only one player remains (all others eliminated)
        $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
        $total_players = $this->getPlayersNumber();
        
        if ($eliminated_count >= $total_players - 1) {
            // Only one player remains, skip to resolve cards directly
            $this->gamestate->nextState("resolveCards");
            return;
        }
        
        $this->activeNextPlayer();
        $round_leader = $this->getGameStateValue("roundLeader");
    
        if ($this->getActivePlayerId() == $round_leader)
        {
            // Back to round leader - check if they had passed this cycle
            $round_leader_passed = $this->getGameStateValue("round_leader_passed_this_cycle");
            
            if ($round_leader_passed) {
                // Round leader passed and cycle completed - resolve cards before next choice
                // Reset the flag for next cycle
                $this->setGameStateValue("round_leader_passed_this_cycle", 0);
                $this->gamestate->nextState("resolveCards");
            } else {
                // Round leader is continuing to play more cards (hasn't passed yet)
                $this->gamestate->nextState("checkRoundThree");
            }
        }
        else
        {
            // Continue with next player
            $this->gamestate->nextState("checkRoundThree");
        }
    }

    public function stPlayCard(): void
    {
        // Reset round leader played card flag when starting a new round of card playing
        // Only skip the reset when the round leader is continuing to play multiple cards in sequence
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        $is_continuing_play = $this->getGameStateValue("round_leader_continuing_play");
        
        if ($current_player == $round_leader && !$is_continuing_play) {
            // This is either a fresh turn for the round leader OR we're starting a new round of card playing
            // Reset the flag so the convert button becomes available again
            $this->setGameStateValue("round_leader_played_card", 0);
            
            // Also reset convert/pray request flag for new round
            $this->setGameStateValue("convert_pray_requested", 0);
            
            // Reset round leader passed flag for new round
            $this->setGameStateValue("round_leader_passed_this_cycle", 0);
            
            // Notify frontend to reset round leader state
            $this->notifyAllPlayers('roundLeaderTurnStart', '', [
                'round_leader_played_card' => 0
            ]);
        }
        
        // Reset the continuing play flag for the next transition
        $this->setGameStateValue("round_leader_continuing_play", 0);

        /* skip handling for play card (https://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php#Flag_to_indicate_a_skipped_state) */
    }

    public function stGlobalOption(): void
    {
        // This state allows the player who played the global disaster to choose avoid/double/normal
        $card_id = (int)$this->getGameStateValue('current_global_disaster');
        $active_player = $this->getActivePlayerId();
        
        // Check if the active player is the one who played this global disaster
        $card_player = $this->getUniqueValueFromDb("SELECT player_id FROM global_disaster_choice WHERE card_id = $card_id");
        
        if ($active_player != $card_player) {
            // Not the player who played the card, skip to next player
            $this->setGameStateValue('current_global_disaster', 0);
            $this->gamestate->nextState("nextPlayerThree");
        }
    }

    public function argGlobalOption(): array
    {
        $card_id = (int)$this->getGameStateValue('current_global_disaster');
        $player_id = $this->getCurrentPlayerId();
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        
        $result = [
            'card_id' => $card_id,
            'player_prayer' => $player_prayer,
            'avoid_cost' => self::GLOBAL_DISASTER_AVOID_COST,
            'double_cost' => self::GLOBAL_DISASTER_DOUBLE_COST,
            'can_avoid' => $player_prayer >= self::GLOBAL_DISASTER_AVOID_COST,
            'can_double' => $player_prayer >= self::GLOBAL_DISASTER_DOUBLE_COST
        ];
        
        // Get card information if available
        if ($card_id > 0) {
            $card_info = $this->getCardWithPlayInfo($card_id);
            if ($card_info) {
                $result['card_info'] = $card_info;
            }
        }
        
        return $result;
    }

    public function stResolveCard(): void
    {
        /* Check if there is a card currently resolving (we come back to this state from others while a card is still resolving) - if so continue to resolve that card
            Else if there are cards remaining move the next available card to resolving
            Else we’re done resolving cards - set the active player to the trick leader and go to PHASE THREE PLAY ACTION CARD
            Resolve (or continue resolving) the resolving card */

        // First check if there's a card currently resolving
        $resolving_card = $this->getCardOnTop('resolving');
        
        // Check if we're returning from amulet resolution for this card
        if ($resolving_card !== null) {
            $amulet_resolution_completed = $this->getGameStateValue("amulet_completed_for_card");
            if ($amulet_resolution_completed == (int)$resolving_card['id']) {
                $this->amuletsResolved = true;
                // Clear the flag so it doesn't interfere with future cards
                $this->setGameStateValue("amulet_completed_for_card", 0);
            }
        }
        
        if ($resolving_card === null) {
            // No card currently resolving, try to get the next card from played cards
            $next_card = $this->getNextCardToResolve();
            
            if ($next_card === null) {
                // No more cards to resolve, check if convert/pray was requested
                $convert_pray_requested = $this->getGameStateValue("convert_pray_requested");
                
                if ($convert_pray_requested) {
                    // Reset the flag and proceed to convert/pray phase
                    $this->setGameStateValue("convert_pray_requested", 0);
                    
                    $this->notifyAllPlayers("cardResolutionComplete", 
                        clienttranslate("Card resolution complete. Proceeding to convert/pray phase"), [
                            'preserve' => 2000 // Show message for 2 seconds
                        ]
                    );
                    $this->gamestate->nextState('convertPray');
                } else {
                    // Normal case - return to card playing
                    // If round leader had passed, make sure they're the active player again
                    $round_leader_passed = $this->getGameStateValue("round_leader_passed_this_cycle");
                    if ($round_leader_passed) {
                        $round_leader = $this->getGameStateValue("roundLeader");
                        $this->gamestate->changeActivePlayer($round_leader);
                    }
                    
                    $this->notifyAllPlayers("cardResolutionComplete", 
                        clienttranslate("Card resolution phase complete"), [
                            'preserve' => 2000 // Show message for 2 seconds
                        ]
                    );
                    $this->gamestate->nextState('continueCardPhase');
                }
                return;
            }
            
            // Move the card from played to resolving
            $this->moveCardToResolving($next_card);
            $resolving_card = $next_card;
            
            // Notify players which card is being resolved
            $card_name = $this->getCardName($resolving_card);
            $this->notifyAllPlayers("cardBeingResolved", 
                clienttranslate("Now resolving: ${card_name}"), [
                    'card_name' => $card_name,
                    'card_id' => $resolving_card['id'],
                    'preserve' => 2500 // Show message for 2.5 seconds
                ]
            );
        }
        
        // Now resolve the card based on its effects
        $resolution_complete = $this->resolveCardEffects($resolving_card, $this->amuletsResolved);
        
        // If resolution is not complete, we need to wait for player input
        if (!$resolution_complete) {
            return; // Exit and wait for player input, method will be called again
        }
        
        // Resolution is complete - move card to resolved if it's still resolving
        $still_resolving = $this->getCardOnTop('resolving');
        if ($still_resolving && $still_resolving['id'] == $resolving_card['id']) {
            $this->moveCardToResolved($resolving_card);
        }
        
        // Reset amulets flag for the next card
        $this->amuletsResolved = false;
        
        // After completing card resolution, process any remaining cards
        // Use a simple loop to avoid state transition issues
        $loop_counter = 0;
        while (true) {
            $loop_counter++;
            if ($loop_counter > 20) { // Safety limit
                $this->notifyAllPlayers("error", "Too many cards in resolution chain - stopping", []);
                break;
            }
            
            $next_card = $this->getNextCardToResolve();
            if ($next_card === null) {
                break; // No more cards to resolve
            }
            
            // Move next card to resolving and process it
            $this->moveCardToResolving($next_card);
            
            $card_name = $this->getCardName($next_card);
            $this->notifyAllPlayers("cardBeingResolved", 
                clienttranslate("Now resolving: ${card_name}"), [
                    'card_name' => $card_name,
                    'card_id' => $next_card['id'],
                    'preserve' => 2500
                ]
            );
            
            $resolution_complete = $this->resolveCardEffects($next_card, false);
            
            if (!$resolution_complete) {
                return; // Need player input, exit and wait
            }
            
            // Move completed card to resolved
            $still_resolving = $this->getCardOnTop('resolving');
            if ($still_resolving && $still_resolving['id'] == $next_card['id']) {
                $this->moveCardToResolved($next_card);
            }
        }
        
        // All cards processed, check if convert/pray was requested
        $convert_pray_requested = $this->getGameStateValue("convert_pray_requested");
        
        if ($convert_pray_requested) {
            // Reset the flag and proceed to convert/pray phase
            $this->setGameStateValue("convert_pray_requested", 0);
            
            $this->notifyAllPlayers("cardResolutionComplete", 
                clienttranslate("Card resolution complete. Proceeding to convert/pray phase"), [
                    'preserve' => 2000 // Show message for 2 seconds
                ]
            );
            $this->gamestate->nextState('convertPray');
        } else {
            // Normal case - return to card playing
            $this->notifyAllPlayers("cardResolutionComplete", 
                clienttranslate("Card resolution phase complete"), [
                    'preserve' => 2000 // Show message for 2 seconds
                ]
            );
            $this->gamestate->nextState('continueCardPhase');
        }

    }
    
    /**
     * Get the next player in turn order based on player_no
     */
    private function getNextPlayerInTurnOrder(int $current_player_id): int
    {
        // Get all players ordered by player_no
        $players = $this->getObjectListFromDb("SELECT player_id, player_no FROM player ORDER BY player_no ASC");
        
        // Find current player's position
        $current_position = null;
        foreach ($players as $index => $player) {
            if ((int)$player['player_id'] == $current_player_id) {
                $current_position = $index;
                break;
            }
        }
        
        // If current player not found, return first player
        if ($current_position === null) {
            return (int)$players[0]['player_id'];
        }
        
        // Get next player, wrapping around to first if at end
        $next_position = ($current_position + 1) % count($players);
        return (int)$players[$next_position]['player_id'];
    }
    
    /**
     * Get the next card to resolve from played cards (FIFO order)
     */
    private function getNextCardToResolve(): ?array
    {
        // Get the card with the lowest play_order from both tables
        // Use aliases to match expected field names
        $disaster_query = "SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, play_order, played_by, target_player 
                          FROM disaster_card 
                          WHERE card_location = 'played' AND play_order IS NOT NULL 
                          ORDER BY play_order ASC LIMIT 1";
        $bonus_query = "SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, play_order, played_by, target_player 
                       FROM bonus_card 
                       WHERE card_location = 'played' AND play_order IS NOT NULL 
                       ORDER BY play_order ASC LIMIT 1";
        
        $disaster_card = $this->getObjectFromDb($disaster_query);
        $bonus_card = $this->getObjectFromDb($bonus_query);
        
        // Return the card with the lower play_order
        if ($disaster_card === null && $bonus_card === null) {
            return null;
        } elseif ($disaster_card === null) {
            return $bonus_card;
        } elseif ($bonus_card === null) {
            return $disaster_card;
        } else {
            // Both exist, return the one with lower play_order
            return ($disaster_card['play_order'] <= $bonus_card['play_order']) ? $disaster_card : $bonus_card;
        }
    }
    
    /**
     * Move a card to resolving location
     */
    private function moveCardToResolving(array $card): void
    {
        $card_id = $card['id'];
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        
        if ($card_type === CardType::GlobalDisaster->value || $card_type === CardType::LocalDisaster->value) {
            $this->disasterCards->moveCard($card_id, 'resolving');
        } else {
            $this->bonusCards->moveCard($card_id, 'resolving');
        }
    }
    
    /**
     * Get the top card from a location (checks both disaster and bonus cards)
     */
    private function getCardOnTop(string $location): ?array
    {
        // Try disaster cards first
        $card = $this->disasterCards->getCardOnTop($location);
        if ($card !== null) {
            return $card;
        }
        
        // Try bonus cards
        $card = $this->bonusCards->getCardOnTop($location);
        if ($card !== null) {
            return $card;
        }
        
        return null;
    }
    
    /**
     * Resolve card effects and direct to appropriate state
     * @param array $card The card to resolve
     * @param bool $amulets_resolved Whether amulets have already been resolved for this card
     */
    private function resolveCardEffects(array $card, bool $amulets_resolved = false): bool
    {
        // If this is a fresh card resolution (not returning from amulet/dice resolution), reset flags
        if (!$amulets_resolved) {
            $this->amuletsResolved = false;
            $this->playerUsedAmulet = [];
            $this->diceResults = [];
            $this->multiactiveAmuletPlayers = [];
            
            // Only reset completion flags if this is truly a new card (different card ID)
            $current_card_id = (int)$card['id'];
            $dice_completed_for_card = $this->getGameStateValue("dice_completed_for_card");
            $discard_completed_for_card = $this->getGameStateValue("discard_completed_for_card");
            
            if ($dice_completed_for_card != $current_card_id) {
                $this->setGameStateValue("dice_completed_for_card", 0);
            }
            if ($discard_completed_for_card != $current_card_id) {
                $this->setGameStateValue("discard_completed_for_card", 0);
            }
        }
        
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        $card_id = (int)$card['id'];
        
        // Get the full card information including who played it and who it targets
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        $played_by = $card_play_info['played_by'] ? (int)$card_play_info['played_by'] : null;
        $target_player = $card_play_info['target_player'] ? (int)$card_play_info['target_player'] : null;
        
        // Get card name for notifications
        $card_name = $this->getCardName($card);
        
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type $card_type, arg $card_type_arg");
        }
        
        // Check for the first non-zero attribute to determine the next state
        // Priority order: discard -> target_selection -> dice_roll -> amulet_decision -> recover_leader -> keep_card
        
        $discard_completed_for_card = $this->getGameStateValue("discard_completed_for_card");
        if ($effects['discard'] > 0 && $discard_completed_for_card != $card_id) {
            // Mark discard as being processed for this card
            $this->setGameStateValue("discard_completed_for_card", $card_id);
            $this->gamestate->nextState('discard');
            return false; // Resolution not complete, waiting for player input
        }
        
        // All local disaster cards require target selection first
        if ($card_type === CardType::LocalDisaster->value) {
            // Check if target is already selected
            if ($target_player === null) {
                // Special handling for single-player scenarios
                $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
                $total_players = $this->getPlayersNumber();
                
                if ($eliminated_count >= $total_players - 1) {
                    // Only one player remains, local disasters can't target anyone
                    $this->notifyAllPlayers('message', 
                        clienttranslate('Only one player remains. ${card_name} has no valid targets and will be discarded.'), [
                            'card_name' => $card_name
                        ]);
                    return true; // Resolution complete, skip effects
                }
                
                // Special handling for Temple Destroyed card
                if ($card_type_arg === LocalDisasterCard::TempleDestroyed->value) {
                    $players_with_temples = $this->getPlayersWithTemples();
                    if (empty($players_with_temples)) {
                        // No players have temples, skip target selection and continue with base effects
                        $this->notifyAllPlayers('message', 
                            clienttranslate('No players have temples to destroy. Skipping target selection.'), []);
                        // Apply base effects without temple destruction
                        if (!$amulets_resolved) {
                            $this->applyBasicCardEffects($card, $effects);
                        }
                        return true; // Resolution complete
                    }
                }
                
                // Set the active player to the one who played the card
                $this->gamestate->changeActivePlayer($played_by);
                $this->gamestate->nextState('selectTargets');
                return false; // Resolution not complete, waiting for target selection
            }
        }
        
        if ($effects['convert_to_religion'] === "roll_d6" || $effects['convert_to_religion'] > 0) {
            // Convert to religion cards with specific targets need target selection first
            if ($target_player === null) {
                $this->gamestate->nextState('selectTargets');
                return false; // Resolution not complete, waiting for target selection
            }
        }
        
        // Check for dice roll requirements first (only if dice haven't been rolled yet)
        $diceNeeded = $effects['happiness_effect'] === "roll_d6" || 
                      $effects['prayer_effect'] === "roll_d6" || 
                      $effects['convert_to_religion'] === "roll_d6";
        
        $dice_completed_for_card = $this->getGameStateValue("dice_completed_for_card");
        if ($diceNeeded && $dice_completed_for_card != $card_id) {
            // Mark dice rolling as being processed for this card and reset all player dice
            $this->setGameStateValue("dice_completed_for_card", $card_id);
            $this->DbQuery("UPDATE player SET player_die = 0");
            // Dice are needed but haven't been rolled yet for this card
            $this->gamestate->nextState('rollDice');
            return false; // Resolution not complete, waiting for dice roll
        }
        
        // After dice rolls (or if no dice needed), check if card has any negative effects that could be mitigated by amulets
        // Amulets only protect against family_dies and convert_to_atheist effects
        $hasNegativeEffects = ($effects['family_dies'] > 0) || 
                             ($effects['convert_to_atheist'] > 0);
        
        if ($hasNegativeEffects && !$amulets_resolved) {
            // Before transitioning to amulet resolution, check if anyone has amulets
            if ($this->anyPlayerHasAmulets($card, $played_by, $target_player)) {
                $this->gamestate->nextState('resolveAmulets');
                return false; // Resolution not complete, waiting for amulet decisions
            } else {
                // No one has amulets, skip amulet resolution
                $this->notifyAllPlayers('amuletPhaseSkipped', 
                    clienttranslate('No players have amulets to use'), []);
            }
        }
        
        if ($effects['recover_leader'] === true) {
            // Check if player already has a leader
            $current_chief = (int)$this->getUniqueValueFromDb("SELECT player_chief FROM player WHERE player_id = $played_by");
            
            if ($current_chief === 0) {
                // Player doesn't have a chief, give them one
                $this->DbQuery("UPDATE player SET player_chief = 1 WHERE player_id = $played_by");
                
                $this->notifyAllPlayers('leaderRecovered', clienttranslate('${player_name} gained a new leader'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by)
                ]);
                
                // Apply basic card effects with amulet protection if needed
                if (!$amulets_resolved) {
                    $this->applyBasicCardEffects($card, $effects);
                } else {
                    $this->applyBasicCardEffectsWithAmulets($card, $effects);
                }
                
                return true; // Resolution complete
            } else {
                // Player already has a chief, skip leader recovery and just apply base card effects
                $this->notifyAllPlayers('leaderAlreadyPresent', clienttranslate('${player_name} already has a leader'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by)
                ]);
                
                // Continue to base card effects processing (don't return here)
            }
        }
        
        if ($effects['keep_card'] === true || $effects['keep_card'] === 1) {
            // Increment temple or amulet counter based on card type
            if ($card_type === CardType::Bonus->value && $card_type_arg === BonusCard::Temple->value) {
                $this->DbQuery("UPDATE player SET player_temple = player_temple + 1 WHERE player_id = $played_by");
                $new_temple_count = (int)$this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $played_by");
                
                // Track statistics: temple built
                $this->incStat(1, 'temples_built', $played_by);
                
                $this->notifyAllPlayers('templeIncremented', clienttranslate('${player_name} gained a temple'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by),
                    'temple_count' => $new_temple_count
                ]);
            } elseif ($card_type === CardType::Bonus->value && $card_type_arg === BonusCard::Amulets->value) {
                $this->DbQuery("UPDATE player SET player_amulet = player_amulet + 1 WHERE player_id = $played_by");
                
                // Track statistics: amulet gained
                $this->incStat(1, 'amulets_gained', $played_by);
                
                $this->notifyAllPlayers('amuletIncremented', clienttranslate('${player_name} gained an amulet'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by)
                ]);
            }
            
            // Apply basic effects - with amulet protection if amulets were resolved
            if (!$amulets_resolved) {
                $this->applyBasicCardEffects($card, $effects);
            } else {
                $this->applyBasicCardEffectsWithAmulets($card, $effects);
            }
            
            return true; // Resolution complete
        }
        
        // If no special effects, apply basic effects and continue resolving
        if (!$amulets_resolved) {
            $this->applyBasicCardEffects($card, $effects);
        } else {
            $this->applyBasicCardEffectsWithAmulets($card, $effects);
        }
        
        return true; // Resolution complete
    }

    /**
     * Get card effects from the constants array
     */
    private function getCardEffects(int $card_type, int $card_type_arg): ?array
    {
        global $CARD_EFFECTS;
        
        if (!isset($CARD_EFFECTS) || $CARD_EFFECTS === null) {
            throw new \BgaVisibleSystemException("CARD_EFFECTS is not available - this should not happen as constants are included at class level");
            throw new \BgaVisibleSystemException("Card effects not available - system error");
        }
        
        // Get effects for card type and type_arg

        
        if (!isset($CARD_EFFECTS[$card_type])) {
            $available_types = array_keys($CARD_EFFECTS);
            return null;
        }
        
        if (!isset($CARD_EFFECTS[$card_type][$card_type_arg])) {
            $available_args = array_keys($CARD_EFFECTS[$card_type]);
            return null;
        }
        
        return $CARD_EFFECTS[$card_type][$card_type_arg];
    }
    
    /**
     * Apply basic card effects (prayer, happiness, convert to atheist)
     */
    private function applyBasicCardEffects(array $card, array $effects): void
    {
        $card_id = (int)$card['id'];
        $card_type = (int)$card['type'];
        
        // Get the full card information including who played it and who it targets
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        $played_by = $card_play_info['played_by'] !== null ? (int)$card_play_info['played_by'] : null;
        
        // Track card play statistics for the player who played the card
        if ($played_by !== null) {
            $this->incStat(1, 'cards_played', $played_by);
            
            // Track card type statistics
            if ($card_type === CardType::GlobalDisaster->value) {
                $this->incStat(1, 'total_global_disasters');
            } elseif ($card_type === CardType::LocalDisaster->value) {
                $this->incStat(1, 'total_local_disasters');
            } elseif ($card_type === CardType::Bonus->value) {
                $this->incStat(1, 'total_bonus_cards');
            }
        }
        
        // Track card play statistics for the player who played the card
        if ($played_by !== null) {
            $this->incStat(1, 'cards_played', $played_by);
            
            // Track card type statistics
            if ($card_type === CardType::GlobalDisaster->value) {
                $this->incStat(1, 'total_global_disasters');
            } elseif ($card_type === CardType::LocalDisaster->value) {
                $this->incStat(1, 'total_local_disasters');
            } elseif ($card_type === CardType::Bonus->value) {
                $this->incStat(1, 'total_bonus_cards');
            }
        }
        $target_player = $card_play_info['target_player'] !== null ? (int)$card_play_info['target_player'] : null;
        
        // Replace "roll_d6" placeholders with actual dice results if dice were rolled
        $dice_completed_for_card = $this->getGameStateValue("dice_completed_for_card");
        if ($dice_completed_for_card == $card_id) {
            // Dice were rolled for this card, replace the placeholders
            $effects = $this->replaceDicePlaceholders($effects);
        }
        
        // Handle global disasters with player choices
        if ($card_type === CardType::GlobalDisaster->value) {
            $this->applyGlobalDisasterEffects($card_id, $effects, $played_by);
        } else {
            // Handle local disasters and bonus cards
            $this->applyTargetedCardEffects($card_id, $effects, $played_by, $target_player);
        }
        
        // This will be implemented to apply the basic effects like prayer_effect, happiness_effect, convert_to_atheist
    }

    /**
     * Replace "roll_d6" placeholders in effects with actual dice results
     * This uses the first player's dice result for cards that affect all players equally
     */
    private function replaceDicePlaceholders(array $effects): array
    {
        // For cards that need dice replacement, we need to get a representative dice result
        // For global effects, we can use any player's result since they should all be the same card
        $dice_result = 1; // Default
        
        // Get any player's dice result from the database
        $any_player_dice = $this->getObjectFromDB("SELECT player_die FROM player WHERE player_die > 0 LIMIT 1");
        if ($any_player_dice) {
            $dice_result = (int)$any_player_dice['player_die'];
        }
        
        // Replace dice placeholders
        $processed_effects = $effects;
        if ($processed_effects['happiness_effect'] === "roll_d6") {
            $processed_effects['happiness_effect'] = $dice_result;
        }
        if ($processed_effects['prayer_effect'] === "roll_d6") {
            $processed_effects['prayer_effect'] = $dice_result;
        }
        if ($processed_effects['convert_to_religion'] === "roll_d6") {
            $processed_effects['convert_to_religion'] = $dice_result;
        }
        
        return $processed_effects;
    }

    /**
     * Apply global disaster effects considering each player's choice
     */
    private function applyGlobalDisasterEffects(int $card_id, array $effects, ?int $played_by): void
    {
        // Update each player's aux score to their current family count before applying global disaster
        $all_players = $this->loadPlayersBasicInfos();
        foreach ($all_players as $player_id => $player) {
            $family_count = $this->getFamilyCount($player_id);
            $this->dbSetAuxScore($player_id, $family_count);
        }
        
        // Get the choice made by the card player (only one choice per global disaster)
        $card_player_choice = $this->getObjectFromDb("
            SELECT player_id, choice, cost_paid FROM global_disaster_choice 
            WHERE card_id = $card_id
            LIMIT 1
        ");
        
        $card_player_id = $played_by; // The player who played the card
        $choice = 'normal'; // Default if no choice was made
        $cost_paid = 0;
        
        if ($card_player_choice) {
            $choice = $card_player_choice['choice'];
            $cost_paid = (int)$card_player_choice['cost_paid'];
        }
        
        // Get all players with their current stats
        $sql = "SELECT player_id, player_prayer, player_happiness, player_family, player_temple, player_amulet 
                FROM player 
                WHERE player_eliminated = 0";
        $players = $this->getObjectListFromDb($sql);

        foreach ($players as $player) {
            $player_id = (int)$player['player_id'];
            
            // Replace dice placeholders with this player's individual dice result
            $player_effects = $effects;
            $dice_completed_for_card = $this->getGameStateValue("dice_completed_for_card");
            if ($dice_completed_for_card == $card_id) {
                // Get this specific player's dice result
                $dice_result = (int)$this->getUniqueValueFromDb("SELECT player_die FROM player WHERE player_id = $player_id");
                if ($dice_result === 0) {
                    $dice_result = 1; // Default to 1 if no result stored
                }
                
                // Replace dice placeholders for this player
                if ($player_effects['happiness_effect'] === "roll_d6") {
                    $player_effects['happiness_effect'] = $dice_result;
                }
                if ($player_effects['prayer_effect'] === "roll_d6") {
                    $player_effects['prayer_effect'] = $dice_result;
                }
                if ($player_effects['convert_to_religion'] === "roll_d6") {
                    $player_effects['convert_to_religion'] = $dice_result;
                }
            }
            
            // Calculate effect multiplier based on choice and player
            $multiplier = 1.0; // Default: normal effect
            
            if ($choice === 'avoid') {
                // Only the card player avoids the effect completely
                $multiplier = ($player_id === $card_player_id) ? 0.0 : 1.0;
            } elseif ($choice === 'double') {
                // Everyone (including the card player) gets double effect
                $multiplier = 2.0;
            }
            
            // For normal effect, apply silently and send generic notification afterwards
            if ($choice === 'normal') {
                $this->applyCardEffects($player_id, $player_effects); // Apply without notification
            } else {
                // Apply effects to this player with the calculated multiplier (with individual notifications)
                $this->applyEffectsToPlayer($player_id, $player_effects, $multiplier, $choice);
            }
        }
        
        // Send generic notification for normal global effects
        if ($choice === 'normal') {
            $effect_text = $this->getEffectsText($effects);
            $this->notifyAllPlayers('globalEffectApplied', 
                clienttranslate('All players ${effect_type}: ${effect_text}'), [
                    'effect_text' => $effect_text,
                    'effect_type' => $this->getEffectTypeText($effects),
                    'effects' => $effects,
                    'multiplier' => 1.0,
                    'choice' => $choice
                ]);
        }
        
        // Clear choices after applying effects
        $this->clearGlobalDisasterChoices($card_id);
    }

    /**
     * Apply targeted card effects (local disasters, bonus cards)
     */
    private function applyTargetedCardEffects(int $card_id, array $effects, ?int $played_by, ?int $target_player): void
    {
        if ($target_player !== null) {
            // Apply effects to the specific target
            $this->applyEffectsToPlayer($target_player, $effects, 1.0, 'normal');
        } else {
            // Apply effects to the player who played the card (for bonus cards)
            if ($played_by !== null) {
                $this->applyEffectsToPlayer($played_by, $effects, 1.0, 'normal');
            }
        }
    }

    /**
     * Apply effects to a specific player
     */
    private function applyEffectsToPlayer(int $player_id, array $effects, float $multiplier, string $choice_type): void
    {
        $effects_to_apply = [];
        
        // Handle prayer effects (prayer is not affected by amulets - always apply full effect)
        if (isset($effects['prayer_effect']) && $effects['prayer_effect'] != 0) {
            $prayer_effect = (int)($effects['prayer_effect'] * $multiplier);
            if ($prayer_effect != 0) {
                $effects_to_apply['prayer_effect'] = $prayer_effect;
            }
        }
        
        // Handle happiness effects
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            $happiness_effect = (int)($effects['happiness_effect'] * $multiplier);
            if ($happiness_effect != 0) {
                $effects_to_apply['happiness_effect'] = $happiness_effect;
            }
        }
        
        // Handle family-related effects (convert_to_atheist, family_dies) - these can be blocked by amulets
        if (isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0) {
            $convert_to_atheist = (int)($effects['convert_to_atheist'] * $multiplier);
            if ($convert_to_atheist > 0) {
                $effects_to_apply['convert_to_atheist'] = $convert_to_atheist;
            }
        }
        
        if (isset($effects['family_dies']) && $effects['family_dies'] > 0) {
            $family_dies = (int)($effects['family_dies'] * $multiplier);
            if ($family_dies > 0) {
                $effects_to_apply['family_dies'] = $family_dies;
            }
        }
        
        // Apply the calculated effects using the existing applyCardEffects method
        if (!empty($effects_to_apply)) {
            $this->applyCardEffects($player_id, $effects_to_apply);
            
            // Determine if this is a bonus card (positive effects) or disaster card (negative effects)
            $has_positive_effects = isset($effects['prayer_effect']) || isset($effects['happiness_effect']);
            $has_negative_effects = isset($effects['family_dies']) || isset($effects['convert_to_atheist']);
            
            // Create appropriate message based on card type, multiplier, and choice
            $message_key = '';
            if ($multiplier === 0.0) {
                $message_key = clienttranslate('${player_name} is protected from the disaster effects');
            } elseif ($has_positive_effects && !$has_negative_effects) {
                // This is a bonus card with positive effects
                if ($multiplier === 2.0) {
                    $message_key = clienttranslate('${player_name} receives double benefits: ${effect_text}');
                } else {
                    $message_key = clienttranslate('${player_name} receives benefits: ${effect_text}');
                }
            } else {
                // This is a disaster card or mixed effects
                if ($multiplier === 2.0) {
                    $message_key = clienttranslate('${player_name} suffers double effects: ${effect_text}');
                } else {
                    $message_key = clienttranslate('${player_name} suffers normal effects: ${effect_text}');
                }
            }
            
            $this->notifyAllPlayers('effectApplied', $message_key, [
                'player_id' => $player_id,
                'player_name' => $this->getPlayerNameById($player_id),
                'effect_text' => $this->getEffectsText($effects_to_apply),
                'effects' => $effects_to_apply,
                'multiplier' => $multiplier,
                'choice' => $choice_type
            ]);
        } elseif ($multiplier === 0.0) {
            // Notify about protection even if no effects would apply
            $this->notifyAllPlayers('effectApplied', 
                clienttranslate('${player_name} is protected from the disaster effects'), [
                    'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id),
                    'multiplier' => $multiplier,
                    'choice' => $choice_type
                ]);
        }
    }
    
    /**
     * Apply card effects to a specific player
     */
    private function applyCardEffects(int $player_id, array $effects): void
    {
        $updates = [];
        
        // Handle prayer effects
        if (isset($effects['prayer_effect']) && $effects['prayer_effect'] != 0) {
            $updates[] = "player_prayer = GREATEST(0, player_prayer + " . (int)$effects['prayer_effect'] . ")";
        }
        
        // Handle happiness effects
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            // Happiness is capped at 10 and can't go below 0
            $updates[] = "player_happiness = LEAST(10, GREATEST(0, player_happiness + " . (int)$effects['happiness_effect'] . "))";
        }
        
        // Apply all updates in a single query if there are any
        if (!empty($updates)) {
            $sql = "UPDATE player SET " . implode(", ", $updates) . " WHERE player_id = $player_id";
            $this->DbQuery($sql);
            
            // Get the updated player data to send to the UI
            $player_data = $this->getObjectFromDb("SELECT player_prayer as prayer, player_happiness as happiness, 
                                                   player_family as family_count, player_temple as temple_count,
                                                   player_amulet as amulet_count
                                                   FROM player WHERE player_id = $player_id");
            
            // Notify about the stat changes
            $this->notifyAllPlayers('playerCountsChanged', '', array_merge([
                'player_id' => $player_id
            ], $player_data));
        }
        
        // Handle more complex effects like family conversion, family death, etc.
        // These might need separate methods
        if (isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0) {
            $families_to_convert = (int)$effects['convert_to_atheist'];
            $current_families = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $player_id");
            
            // Can only convert as many families as the player has
            $actual_converted = min($families_to_convert, $current_families);
            
            if ($actual_converted > 0) {
                // Remove families from player
                self::DbQuery("UPDATE player SET player_family = GREATEST(0, player_family - $actual_converted) WHERE player_id = $player_id");
                
                // Add to global atheist families pool (global_id = 101)
                self::DbQuery("UPDATE global SET global_value = global_value + $actual_converted WHERE global_id = 101");
                
                // Get updated player data and send notification
                $player_data = $this->getObjectFromDb("SELECT player_prayer as prayer, player_happiness as happiness, 
                                                       player_family as family_count, player_temple as temple_count,
                                                       player_amulet as amulet_count
                                                       FROM player WHERE player_id = $player_id");
                
                // Update UI counters
                $this->notifyAllPlayers('playerCountsChanged', '', array_merge([
                    'player_id' => $player_id
                ], $player_data));
                
                // Notify about the conversion
                $this->notifyAllPlayers('familiesConverted', 
                    clienttranslate('${player_name} loses ${families_count} families to atheism'), [
                        'player_id' => $player_id,
                        'player_name' => $this->getPlayerNameById($player_id),
                        'families_count' => $actual_converted,
                        'families_remaining' => $current_families - $actual_converted
                    ]
                );
                
                // Track statistics: families lost and families converted to atheism
                $this->incStat($actual_converted, 'families_lost', $player_id);
                $this->incStat($actual_converted, 'families_became_atheist', $player_id);
            }
        }
        
        if (isset($effects['family_dies']) && $effects['family_dies'] > 0) {
            $families_to_kill = (int)$effects['family_dies'];
            $current_families = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $player_id");
            
            // Can only kill as many families as the player has
            $actual_killed = min($families_to_kill, $current_families);
            
            if ($actual_killed > 0) {
                // Remove families from player (they die, don't become atheist)
                self::DbQuery("UPDATE player SET player_family = GREATEST(0, player_family - $actual_killed) WHERE player_id = $player_id");
                
                // Get updated player data and send notification
                $player_data = $this->getObjectFromDb("SELECT player_prayer as prayer, player_happiness as happiness, 
                                                       player_family as family_count, player_temple as temple_count,
                                                       player_amulet as amulet_count
                                                       FROM player WHERE player_id = $player_id");
                
                // Update UI counters
                $this->notifyAllPlayers('playerCountsChanged', '', array_merge([
                    'player_id' => $player_id
                ], $player_data));
                
                // Notify about the deaths
                $this->notifyAllPlayers('familiesDied', 
                    clienttranslate('${player_name} loses ${families_count} families to death'), [
                        'player_id' => $player_id,
                        'player_name' => $this->getPlayerNameById($player_id),
                        'families_count' => $actual_killed,
                        'families_remaining' => $current_families - $actual_killed
                    ]
                );
                
                // Track statistics: families lost and families that died
                $this->incStat($actual_killed, 'families_lost', $player_id);
                $this->incStat($actual_killed, 'families_died', $player_id);
            }
        }
        
        // Handle temple destruction
        if (isset($effects['temple_destroyed']) && $effects['temple_destroyed'] > 0) {
            $temples_to_destroy = (int)$effects['temple_destroyed'];
            $current_temples = (int)$this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $player_id");
            
            // Can only destroy as many temples as the player has
            $actual_destroyed = min($temples_to_destroy, $current_temples);
            
            if ($actual_destroyed > 0) {
                // Remove temples from player
                self::DbQuery("UPDATE player SET player_temple = GREATEST(0, player_temple - $actual_destroyed) WHERE player_id = $player_id");
                
                // Get updated player data and send notification
                $player_data = $this->getObjectFromDb("SELECT player_prayer as prayer, player_happiness as happiness, 
                                                       player_family as family_count, player_temple as temple_count,
                                                       player_amulet as amulet_count
                                                       FROM player WHERE player_id = $player_id");
                
                // Update UI counters
                $this->notifyAllPlayers('playerCountsChanged', '', array_merge([
                    'player_id' => $player_id
                ], $player_data));
                
                // Notify about the temple destruction
                $this->notifyAllPlayers('templeDestroyed', 
                    clienttranslate('${player_name} loses ${temples_count} temple(s)'), [
                        'player_id' => $player_id,
                        'player_name' => $this->getPlayerNameById($player_id),
                        'temples_count' => $actual_destroyed,
                        'temples_remaining' => $current_temples - $actual_destroyed
                    ]
                );
                
                // Track statistics: temples destroyed
                $this->incStat($actual_destroyed, 'temples_destroyed', $player_id);
                
                // Track statistics: temples destroyed
                $this->incStat($actual_destroyed, 'temples_destroyed', $player_id);
            }
        }
    }
    
    /**
     * Generate a human-readable text description of effects
     */
    private function getEffectsText(array $effects): string
    {
        $effect_parts = [];
        
        if (isset($effects['prayer_loss']) && $effects['prayer_loss'] > 0) {
            $effect_parts[] = "-{$effects['prayer_loss']} prayer";
        }
        if (isset($effects['prayer_effect']) && $effects['prayer_effect'] > 0) {
            $effect_parts[] = "+{$effects['prayer_effect']} prayer";
        } elseif (isset($effects['prayer_effect']) && $effects['prayer_effect'] < 0) {
            $effect_parts[] = "{$effects['prayer_effect']} prayer";
        }
        
        if (isset($effects['faith_loss']) && $effects['faith_loss'] > 0) {
            $effect_parts[] = "-{$effects['faith_loss']} faith";
        }
        
        if (isset($effects['trade_loss']) && $effects['trade_loss'] > 0) {
            $effect_parts[] = "-{$effects['trade_loss']} trade";
        }
        
        if (isset($effects['culture_loss']) && $effects['culture_loss'] > 0) {
            $effect_parts[] = "-{$effects['culture_loss']} culture";
        }
        
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            $sign = $effects['happiness_effect'] > 0 ? '+' : '';
            $effect_parts[] = "{$sign}{$effects['happiness_effect']} happiness";
        }
        
        if (isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0) {
            $effect_parts[] = "{$effects['convert_to_atheist']} families convert to atheist";
        }
        
        if (isset($effects['family_dies']) && $effects['family_dies'] > 0) {
            $effect_parts[] = "{$effects['family_dies']} families die";
        }
        
        return empty($effect_parts) ? 'no effects' : implode(', ', $effect_parts);
    }
    
    /**
     * Get the type of effect (gain or lose) for generic notifications
     */
    private function getEffectTypeText(array $effects): string
    {
        $has_positive = isset($effects['prayer_effect']) && $effects['prayer_effect'] > 0 ||
                       isset($effects['happiness_effect']) && $effects['happiness_effect'] > 0;
                       
        $has_negative = isset($effects['prayer_loss']) && $effects['prayer_loss'] > 0 ||
                       isset($effects['faith_loss']) && $effects['faith_loss'] > 0 ||
                       isset($effects['trade_loss']) && $effects['trade_loss'] > 0 ||
                       isset($effects['culture_loss']) && $effects['culture_loss'] > 0 ||
                       isset($effects['happiness_effect']) && $effects['happiness_effect'] < 0 ||
                       isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0 ||
                       isset($effects['family_dies']) && $effects['family_dies'] > 0;
        
        if ($has_positive && !$has_negative) {
            return clienttranslate('gain');
        } elseif ($has_negative && !$has_positive) {
            return clienttranslate('lose');
        } else {
            return clienttranslate('are affected by');
        }
    }
    
    /**
     * Move a card from resolving to resolved
     */
    private function moveCardToResolved(array $card): void
    {
        $card_id = $card['id'];
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        
        if ($card_type === CardType::GlobalDisaster->value || $card_type === CardType::LocalDisaster->value) {
            $this->disasterCards->moveCard($card_id, 'resolved');
        } else {
            $this->bonusCards->moveCard($card_id, 'resolved');
        }
        
        // Notify frontend to move card from played to resolved stock
        $this->notifyAllPlayers('cardResolved', clienttranslate('${card_name} has been resolved'), [
            'card_id' => $card_id,
            'card_type' => $card_type,
            'card_type_arg' => $card_type_arg,
            'card_name' => $this->getCardName($card)
        ]);
    }
    
    public function stSelectTarget(): void
    {
        /* Active player must select the player to target with their disaster */
        // Get the currently resolving card
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }
        
        $card_name = $this->getCardName($resolving_card);
        $active_player_id = $this->getActivePlayerId();
        
        // Notify all players about target selection
        $this->notifyAllPlayers("message", 
            clienttranslate('${player_name} must select a target for ${card_name}'), 
            [
                'player_name' => $this->getPlayerNameById($active_player_id),
                'card_name' => $card_name
            ]
        );
    }

    /**
     * Check if any player has amulets that could be used for the current card
     */
    private function anyPlayerHasAmulets(array $card, ?int $played_by, ?int $target_player): bool
    {
        $card_type = (int)$card['type'];
        
        if ($card_type === CardType::LocalDisaster->value && $target_player !== null) {
            // For local disasters, only the target player can use an amulet
            $target_amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $target_player");
            return $target_amulet_count > 0;
        } else {
            // For global effects, check if any player has amulets
            $all_players = $this->loadPlayersBasicInfos();
            foreach ($all_players as $player_id => $player) {
                $amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $player_id");
                if ($amulet_count > 0) {
                    return true;
                }
            }
            return false;
        }
    }

    public function stResolveAmulets(): void
    {
        // Get the currently resolving card to determine who is targeted
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        $card_play_info = $this->getCardWithPlayInfo((int)$resolving_card['id']);
        $target_player = $card_play_info['target_player'] ?? null;
        
        // For local disaster cards, only the target player can use an amulet
        $card_type = (int)$resolving_card['type'];
        $players_who_can_use_amulets = [];
        
        if ($card_type === CardType::LocalDisaster->value && $target_player !== null) {
            // Check if target player has an amulet
            $target_amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $target_player");
            if ($target_amulet_count > 0) {
                $players_who_can_use_amulets[] = $target_player;
            }
        } else {
            // For global effects, all players who have amulets can potentially use them
            $all_players = $this->loadPlayersBasicInfos();
            foreach ($all_players as $player_id => $player) {
                $amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $player_id");
                if ($amulet_count > 0) {
                    $players_who_can_use_amulets[] = $player_id;
                }
            }
        }

        if (empty($players_who_can_use_amulets)) {
            // No players have amulets, proceed directly with card effects
            $this->notifyAllPlayers('amuletPhaseSkipped', 
                clienttranslate('No players have amulets to use'), []);
            
            $this->applyCardEffectsWithoutAmulets($resolving_card);
            return;
        }

        // Set only players with amulets as active
        $this->multiactiveAmuletPlayers = $players_who_can_use_amulets;
        $this->gamestate->setPlayersMultiactive($players_who_can_use_amulets, '');
        
        // Mark that amulet resolution is in progress for this card
        $this->setGameStateValue("amulet_completed_for_card", (int)$resolving_card['id']);
        
        $card_name = $this->getCardName($resolving_card);
        $this->notifyAllPlayers("amuletDecision", 
            clienttranslate('Players with amulets must decide whether to use them against ${card_name}'), 
            [
                'card_name' => $card_name,
                'players_with_amulets' => $players_who_can_use_amulets
            ]
        );
    }

    public function argResolveAmulets(): array
    {
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            return [];
        }

        $current_player_id = $this->getCurrentPlayerId();
        $player_amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $current_player_id");
        
        return [
            'card_id' => $resolving_card['id'],
            'card_name' => $this->getCardName($resolving_card),
            'player_amulet_count' => $player_amulet_count,
            'can_use_amulet' => $player_amulet_count > 0
        ];
    }

    private function applyCardEffectsWithoutAmulets(array $card): void
    {
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$card_type}, arg {$card_type_arg}");
        }

        // Apply the card effects directly (dice results already incorporated if needed)
        if (!empty($this->diceResults)) {
            // Dice were rolled, apply effects with dice results
            $this->applyBasicCardEffectsWithDiceAndAmulets($card, $effects);
        } else {
            // No dice were rolled, apply basic effects
            $this->applyBasicCardEffects($card, $effects);
        }
        
        // Mark amulets as resolved (even though none were used)
        $this->amuletsResolved = true;
        
        // Move card from resolving to resolved and let stResolveCard handle next steps
        $this->moveCardToResolved($card);
        
        // Transition back to card resolution to check for more cards
        $this->gamestate->nextState('beginAllPlay');
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

        // Get existing family, prayer, and happiness counts for all players
        $previous_family = [];
        $previous_prayer = [];
        $previous_happiness = [];
        
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $_) {
            $previous_family[$player_id] = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $player_id");
            $previous_prayer[$player_id] = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
            $previous_happiness[$player_id] = (int)$this->getUniqueValueFromDb("SELECT player_happiness FROM player WHERE player_id = $player_id");
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

        // Players receive prayers (1 per 5 family, extra if not highest, and +1 per temple)
        foreach ($players as $player_id => $_) {
            $family_count = $this->getFamilyCount($player_id);
            $temple_count = (int)$this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $player_id");
            $prayers = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
            $happiness = $happinessScores[$player_id];
            
            $prayers += floor($family_count / 5);
            if ($happinessScores[$player_id] == $happy_value_low) {
                $prayers += 4;
            } elseif ($happinessScores[$player_id] != $happy_value_high) {
                $prayers += 2;
            }
            
            // Add temple bonuses: +1 prayer and +1 happiness per temple
            $prayers += $temple_count;
            $happiness += $temple_count;
            
            // Enforce 0-10 range for happiness
            $happiness = max(0, min(10, $happiness));
            
            self::DbQuery("UPDATE player SET player_prayer = $prayers, player_happiness = $happiness WHERE player_id = $player_id");
        }

        // Track statistics: round completed
        $this->incStat(1, 'total_rounds');
        
        // Check for player elimination (no chief/families)
        foreach ($players as $player_id => $player) {
            if ($this->getFamilyCount($player_id) == 0 && $this->getChiefCount($player_id) == 0) {
                $was_eliminated = (int)$this->getUniqueValueFromDb("SELECT player_eliminated FROM player WHERE player_id = $player_id");
                if ($was_eliminated == 0) {
                    // Track statistics: player eliminated (only count when first eliminated)
                    $this->incStat(1, 'players_eliminated');
                    
                    // Increment score for all remaining (non-eliminated) players
                    self::DbQuery("UPDATE player SET player_score = player_score + 1 WHERE player_eliminated = 0 AND player_id != $player_id");
                    
                }
                self::DbQuery("UPDATE player SET player_eliminated = 1 WHERE player_id = $player_id");
            }
        }

        // Check religions remaining and proceed to end game if only one or zero remain
        $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
        $player_count = count($players);
        if ($eliminated_count >= $player_count - 1) {
            $this->gamestate->nextState('gameOver');
            return;
        }

        // Update round leader to next non-eliminated player in turn order
        $current_leader = (int)$this->getGameStateValue("roundLeader");
        $next_leader = $this->getNextPlayerInTurnOrder($current_leader);
        
        // Skip any eliminated players (with safety counter to prevent infinite loops)
        $attempts = 0;
        $total_players = $this->getPlayersNumber();
        while ((int)$this->getUniqueValueFromDb("SELECT player_eliminated FROM player WHERE player_id = $next_leader") == 1) {
            $next_leader = $this->getNextPlayerInTurnOrder($next_leader);
            $attempts++;
            
            // Safety check: if we've checked all players and they're all eliminated, 
            // something is wrong with the game state
            if ($attempts >= $total_players) {
                throw new \BgaVisibleSystemException("All players appear to be eliminated - this should not happen");
            }
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
            $temple_count = (int)$this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $player_id");
            $eliminated = (int)$this->getUniqueValueFromDb("SELECT player_eliminated FROM player WHERE player_id = $player_id");
            $happiness = (int)$this->getUniqueValueFromDb("SELECT player_happiness FROM player WHERE player_id = $player_id");
            $prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");

            // Compute deltas (difference from previous round)
            $family_delta = $family_count - $previous_family[$player_id];
            $prayer_delta = $prayer - $previous_prayer[$player_id];
            $happiness_delta = $happiness - $previous_happiness[$player_id];

            $family_change = $family_delta >= 0 ? "increased" : "decreased";
            $prayer_change = $prayer_delta >= 0 ? "increased" : "decreased";
            $happiness_change = $happiness_delta >= 0 ? "increased" : "decreased";

            // Add notification for temple bonuses if player has temples
            if ($temple_count > 0) {
                $this->notifyAllPlayers('templeBonus', clienttranslate('${player_name} receives +${temple_count} prayer and +${temple_count} happiness from ${temple_count} temple(s)'), [
                    'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id),
                    'temple_count' => $temple_count
                ]);
            }

            $this->notifyAllPlayers(
                'playerCountsChanged',
                clienttranslate('${player_name}: Families ${family_count} (${family_change} by ${family_delta}), Prayers ${prayer} (${prayer_change} by ${prayer_delta}), Happiness ${happiness} (${happiness_change} by ${happiness_delta})'),
                [
                    'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id),
                    'family_count' => $family_count,
                    'family_change' => $family_change,
                    'family_delta' => abs($family_delta),
                    'prayer' => $prayer,
                    'prayer_change' => $prayer_change,
                    'prayer_delta' => abs($prayer_delta),
                    'happiness' => $happiness,
                    'happiness_change' => $happiness_change,
                    'happiness_delta' => abs($happiness_delta),
                    'eliminated' => $eliminated,
                    'temple_count' => $temple_count
                ]
            );

            if ($eliminated == 1) {
                $this->notifyAllPlayers('playerEliminated', clienttranslate('${player_name} has been eliminated!'), [
                    'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id)
                ]);
            }
        }


        $old_leader = $this->getGameStateValue("roundLeader");
        $this->setGameStateValue("roundLeader", $next_leader);
        
        // Set the new round leader as the active player for the draw phase
        $this->gamestate->changeActivePlayer($next_leader);
        
        // Notify all players about the round leader change
        if ($old_leader != $next_leader) {
            $this->notifyAllPlayers('roundLeaderChanged', clienttranslate('${player_name} is now the round leader'), [
                'player_id' => $next_leader,
                'player_name' => $this->getPlayerNameById($next_leader),
                'old_leader' => $old_leader
            ]);
        }
        
        // Cleanup: Move all resolved cards to discard pile before starting next round
        $this->cleanupResolvedCards();
        
        $this->gamestate->nextState('phaseOneDraw');
    }
    
    /**
     * Move all played and resolved cards to discard pile and clean up UI
     */
    private function cleanupResolvedCards(): void
    {
        // Get all cards from both played and resolved locations
        $played_disaster_cards = $this->disasterCards->getCardsInLocation('played');
        $played_bonus_cards = $this->bonusCards->getCardsInLocation('played');
        $resolved_disaster_cards = $this->disasterCards->getCardsInLocation('resolved');
        $resolved_bonus_cards = $this->bonusCards->getCardsInLocation('resolved');
        
        $all_cards_to_cleanup = array_merge($played_disaster_cards, $played_bonus_cards, $resolved_disaster_cards, $resolved_bonus_cards);
        
        if (!empty($all_cards_to_cleanup)) {
            // Move all disaster cards to discard
            foreach (array_merge($played_disaster_cards, $resolved_disaster_cards) as $card) {
                $this->disasterCards->moveCard($card['id'], 'discard');
            }
            
            // Move all bonus cards to discard  
            foreach (array_merge($played_bonus_cards, $resolved_bonus_cards) as $card) {
                $this->bonusCards->moveCard($card['id'], 'discard');
            }
            
            // Notify frontend to clear all played/resolved card stocks and displays
            $this->notifyAllPlayers('allCardsCleanup', clienttranslate('All played and resolved cards have been discarded for the new round'), [
                'total_cards_count' => count($all_cards_to_cleanup),
                'played_cards_count' => count($played_disaster_cards) + count($played_bonus_cards),
                'resolved_cards_count' => count($resolved_disaster_cards) + count($resolved_bonus_cards),
                'cards_cleaned' => array_map(function($card) {
                    return [
                        'card_id' => $card['id'],
                        'card_type' => $card['type'],
                        'card_type_arg' => $card['type_arg'],
                        'location' => $card['location']
                    ];
                }, $all_cards_to_cleanup)
            ]);
        }
    }


///////////Player Actions /////////////////////
    public function actDrawCardInit(string $type /* either "disaster" or "bonus" */): void
    {
        /* Draws a card and notifies that user of the drawn card
            Notifies UI to draw a card, and how many cards left to draw to reach 5
            UI will update with cards drawn, or waiting once we hit 0 cards remaining
            Checks if all users have drawn 5 cards - if they have, go to INITIAL FINISH */
        
        $this->drawCard_private($type, null, true); // Suppress notifications during initial draw
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

        $player_id = $this->getCurrentPlayerId();

        self::DbQuery( "UPDATE player SET player_happiness = LEAST(10, player_happiness + 1) WHERE player_id = {$this->getActivePlayerId()}");

        // Track statistics: speech given
        $this->incStat(1, 'speeches_given', $player_id);

        // Notify all players
        $this->notifyAllPlayers('giveSpeech', clienttranslate('${player_name} gave a speech'), [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName()
        ]);

        $this->gamestate->nextState();
    }

    public function actConvertAtheists(): void
    {

        $player_id = $this->getCurrentPlayerId();

        // Get current number of atheist families
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        // Move up to two atheist families to the player, if they exist
        $toConvert = min(2, $atheistCount);
        if ($toConvert > 0) {
            self::DbQuery("UPDATE global SET global_value = global_value - $toConvert WHERE global_id = 101");
            self::DbQuery("UPDATE player SET player_family = player_family + $toConvert WHERE player_id = {$player_id}");
        }

        // Track statistics: atheists converted
        $this->incStat($toConvert, 'atheists_converted', $player_id);
        
        // Track statistics: atheists converted
        $this->incStat($toConvert, 'atheists_converted', $player_id);
        
        $this->notifyAllPlayers('convertAtheists', clienttranslate('${player_name} converted ${num_atheists} atheist(s)'), [
                'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName(),
                'num_atheists' => $toConvert
            ]);

        $this->gamestate->nextState();
    }

    public function actConvertBelievers(int $target_player_id): void
    {

        // Move one family from target_player_id to current player
        $current_player_id = $this->getCurrentPlayerId();

        // Get family counts
        $target_family = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $target_player_id");
        $current_family = (int)$this->getUniqueValueFromDb("SELECT player_family FROM player WHERE player_id = $current_player_id");

        // Only transfer if target has at least one family
        if ($target_family > 0) {
            self::DbQuery("UPDATE player SET player_family = player_family - 1 WHERE player_id = $target_player_id");
            self::DbQuery("UPDATE player SET player_family = player_family + 1 WHERE player_id = $current_player_id");

            // Track statistics: believer converted (stolen)
            $this->incStat(1, 'believers_converted', $current_player_id);
            
            $this->notifyAllPlayers('convertBelievers', clienttranslate('${player_name} converted a believer from ${target_name}'), [
                'player_id' => $current_player_id,
                'player_name' => $this->getActivePlayerName(),
                'target_id' => $target_player_id,
                'target_name' => $this->getPlayerNameById($target_player_id)
            ]);
        }
        else{

        }

        $this->gamestate->nextState();
    }

    public function actSacrificeLeader(): void
    {
        /* Increase player's happiness by one */


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

        // Track statistics: leader sacrificed and atheists converted
        $this->incStat(1, 'leader_sacrificed', $player_id);
        $this->incStat($toConvert, 'atheists_converted', $player_id);
        
        $this->notifyAllPlayers('sacrificeLeader', clienttranslate('${player_name}\'s leader gave a massive speeach
                                    and sacrificed themself, converting ${num_atheists} atheists'), [
                'player_id' => $player_id,
                'player_no' => $this->getPlayerNoById($player_id),
                'player_name' => $this->getActivePlayerName(),
                'num_atheists' => $toConvert
            ]);
        
        $this->gamestate->nextState();
    }


    /***************************************/

    /***** Play card actions ******/
    public function actPlayCard(int $card_id): void
    {
        // 1. Check if action is allowed
        $this->checkAction('actPlayCard');

        // Start database transaction to prevent partial updates
        $this->DbQuery("START TRANSACTION");
        
        try {
            // 2. Get current player (cast to int since moveCard expects int)
            $player_id = (int)$this->getActivePlayerId();

        // 3. Validate the card belongs to the player by checking both decks separately (avoid UNION deadlock)
        $card_in_hand = null;
        
        // Check disaster cards first
        $disaster_check = $this->getObjectFromDB("
            SELECT card_id, card_type, card_type_arg 
            FROM disaster_card 
            WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
        ");
        
        if ($disaster_check !== null) {
            $card_in_hand = $disaster_check;
        } else {
            // Check bonus cards if not found in disaster
            $bonus_check = $this->getObjectFromDB("
                SELECT card_id, card_type, card_type_arg 
                FROM bonus_card 
                WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
            ");
            $card_in_hand = $bonus_check;
        }
        
        if ($card_in_hand === null) {
            throw new \BgaUserException("This card is not in your hand");
        }
        
        // Get the card for further processing
        $card = $this->getCard($card_id);
        if ($card === null) {
            throw new \BgaUserException("Card not found");
        }

        // 4. Get prayer cost before checking if card can be played
        $prayer_cost = $this->getCardPrayerCost($card);

        // 5. Apply game rules validation here
        // Check if this card can be played and show warnings for ineffective plays
        $this->validateCardPlay($player_id, $card);

        // 5a. Deduct prayer cost from player
        if ($prayer_cost > 0) {
            $this->DbQuery("UPDATE player SET player_prayer = player_prayer - $prayer_cost WHERE player_id = $player_id");
        }

        // 6. Move card to played location
        $this->moveCard($card_id, 'played', $player_id);

        // 6a. Track if round leader has played a card this turn
        $round_leader = $this->getGameStateValue("roundLeader");
        if ($player_id == $round_leader) {
            $this->setGameStateValue("round_leader_played_card", 1);
        }

        // 6b. Set the play order for FIFO resolution and record who played it
        $target_player = $this->determineCardTarget($card, $player_id);
        $this->setCardPlayOrder($card_id, $player_id, $target_player);

        // 7. Get updated player stats for notifications (after prayer deduction)
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        $disaster_cards_in_hand = $this->disasterCards->countCardInLocation("hand", $player_id);
        $bonus_cards_in_hand = $this->bonusCards->countCardInLocation("hand", $player_id);
        $total_cards_in_hand = $disaster_cards_in_hand + $bonus_cards_in_hand;

        // 8. Send consolidated notification to all players about the card play
        $notification_message = clienttranslate('${player_name} plays ${card_name}');
        $notification_args = [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'card_id' => $card_id,
            'card_name' => $this->getCardName($card),
            'card_type' => $card['type'],
            'card_type_arg' => $card['type_arg'],
            'played_by' => $player_id,
            'target_player' => $target_player,
            'prayer_cost' => $prayer_cost,
            'new_prayer_total' => $player_prayer,
            'card_count' => $total_cards_in_hand
        ];
        
        // Create comprehensive message including prayer cost if applicable
        if ($prayer_cost > 0) {
            if ($target_player !== null && $target_player !== $player_id) {
                $notification_message = clienttranslate('${player_name} spends ${prayer_cost} prayer to play ${card_name} targeting ${target_name}');
                $notification_args['target_name'] = $this->getPlayerNameById($target_player);
            } else {
                $notification_message = clienttranslate('${player_name} spends ${prayer_cost} prayer to play ${card_name}');
            }
        } else {
            // No prayer cost, but check for targeting
            if ($target_player !== null && $target_player !== $player_id) {
                $notification_message = clienttranslate('${player_name} plays ${card_name} targeting ${target_name}');
                $notification_args['target_name'] = $this->getPlayerNameById($target_player);
            }
        }
        
        $this->notifyAllPlayers('cardPlayed', $notification_message, $notification_args);

        // 8a. If round leader played a card, notify about button state change
        if ($player_id == $round_leader) {
            $this->notifyAllPlayers('roundLeaderPlayedCard', '', [
                'round_leader_played_card' => 1
            ]);
        }

        if ((int)$card['type'] === CardType::GlobalDisaster->value) {
            // Initialize choice for the player who played this global disaster
            $this->initializeGlobalDisasterChoice($card_id, $player_id);
            // Store the card ID for the choice actions
            $this->setGameStateValue('current_global_disaster', $card_id);
            
            // Commit transaction before state change
            $this->DbQuery("COMMIT");
            $this->gamestate->nextState('phaseThreeCheckGlobal');
            return;
        } else {
            // Check if current player is round leader
            $current_player = $this->getActivePlayerId();
            $round_leader = $this->getGameStateValue("roundLeader");
            
            // Commit transaction before state change
            $this->DbQuery("COMMIT");
            
            if ($current_player == $round_leader) {
                // Round leader can play again, but mark that they're continuing to play
                $this->setGameStateValue("round_leader_continuing_play", 1);
                $this->gamestate->nextState('playAgain');
            } else {
                // Non-round leader moves to next player
                $this->gamestate->nextState('nextPlayerThree');
            }
            return;
        }
        
        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->DbQuery("ROLLBACK");
            throw $e;
        }
    }

    public function actPlayCardPass(): void
    {
        // Check if action is allowed
        $this->checkAction('actPlayCardPass');
        
        // Start database transaction for consistency
        $this->DbQuery("START TRANSACTION");
        
        try {
            // Clear the continuing play flag since the player is passing
            $this->setGameStateValue("round_leader_continuing_play", 0);
            
            // Check if the round leader is passing
            $player_id = $this->getActivePlayerId();
            $round_leader = $this->getGameStateValue("roundLeader");
            
            if ($player_id == $round_leader) {
                // Round leader is passing - set flag so cards resolve when cycle returns to them
                $this->setGameStateValue("round_leader_passed_this_cycle", 1);
            }
            
            $players = $this->loadPlayersBasicInfos();
            $player = $players[$player_id];
            
            // Count cards in hand
            $disaster_cards = $this->getObjectFromDB("SELECT COUNT(*) as count FROM disaster_card WHERE card_location = 'hand' AND card_location_arg = $player_id");
            $bonus_cards = $this->getObjectFromDB("SELECT COUNT(*) as count FROM bonus_card WHERE card_location = 'hand' AND card_location_arg = $player_id");
            $total_cards = ($disaster_cards['count'] ?? 0) + ($bonus_cards['count'] ?? 0);
            
            // Check if player has no cards and insufficient prayer for auto-pass
            if ($total_cards == 0 && $player['player_prayer'] < 5) {
                $player_name = $player['player_name'];
                $this->notifyAllPlayers('message', 
                    clienttranslate('${player_name} was automatically passed (no cards and insufficient prayer to buy more)'), 
                    [
                        'player_name' => $player_name
                    ]
                );
            }

            // Commit transaction before state change
            $this->DbQuery("COMMIT");
            $this->gamestate->nextState('nextPlayerThree');
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->DbQuery("ROLLBACK");
            throw $e;
        }
    }

    public function actSayConvert(): void
    {
        // Clear the continuing play flag since the round leader is ending the cycle
        $this->setGameStateValue("round_leader_continuing_play", 0);
        
        // Set flag to indicate convert/pray was requested
        $this->setGameStateValue("convert_pray_requested", 1);
        
        // Cycle ends immediately - resolve cards and then proceed to convert/pray
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
        
        $player_id = (int)$this->getCurrentPlayerId();
        
        // Check if player has enough prayer points
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        if ($player_prayer < 5) {
            throw new \BgaUserException("You need 5 prayer points to buy a card");
        }
        
        // Deduct 5 prayer points
        $this->DbQuery("UPDATE player SET player_prayer = player_prayer - 5 WHERE player_id = $player_id");
        
        // Draw the card using the existing private function, passing player_id explicitly
        $this->drawCard_private($type, $player_id);
        
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
        
    }

    public function actAvoidGlobal(): void
    {
        $this->checkAction('actAvoidGlobal');
        $player_id = (int)$this->getCurrentPlayerId();
        $card_id = (int)$this->getGameStateValue('current_global_disaster');
        
        if ($card_id <= 0) {
            throw new \BgaUserException("No global disaster card to make choice for");
        }
        
        // Verify this player played the global disaster card
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        if ($card_play_info['played_by'] != $player_id) {
            throw new \BgaUserException("Only the player who played this global disaster can choose to avoid it");
        }
        
        // Validate the player has enough prayer points to avoid
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        if ($player_prayer < self::GLOBAL_DISASTER_AVOID_COST) {
            throw new \BgaUserException("You need at least " . self::GLOBAL_DISASTER_AVOID_COST . " prayer points to avoid this disaster");
        }
        
        // Deduct the avoid cost immediately
        $this->DbQuery("UPDATE player SET player_prayer = player_prayer - " . self::GLOBAL_DISASTER_AVOID_COST . " WHERE player_id = $player_id");
        
        // Record the player's choice to avoid
        $this->setGlobalDisasterChoice($card_id, $player_id, 'avoid', self::GLOBAL_DISASTER_AVOID_COST);
        
        // Track statistics: global disaster avoided
        $this->incStat(1, 'global_disasters_avoided', $player_id);
        
        // Notify about the choice and cost
        $this->notifyAllPlayers('globalDisasterChoice', 
            clienttranslate('${player_name} spends ${cost} prayer points to avoid their global disaster (only they will be protected)'), [
                'player_id' => $player_id,
                'player_name' => $this->getCurrentPlayerName(),
                'choice' => 'avoid',
                'cost' => self::GLOBAL_DISASTER_AVOID_COST,
                'card_id' => $card_id,
                'new_prayer_total' => $player_prayer - self::GLOBAL_DISASTER_AVOID_COST
            ]);

        // Clear the stored card ID and transition to card resolution
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Global disaster choice made, proceed to resolve the card
        $this->gamestate->nextState("resolveCards");
    }

    public function actDoubleGlobal(): void
    {
        $this->checkAction('actDoubleGlobal');
        $player_id = (int)$this->getCurrentPlayerId();
        $card_id = (int)$this->getGameStateValue('current_global_disaster');
        
        if ($card_id <= 0) {
            throw new \BgaUserException("No global disaster card to make choice for");
        }
        
        // Verify this player played the global disaster card
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        if ($card_play_info['played_by'] != $player_id) {
            throw new \BgaUserException("Only the player who played this global disaster can choose to double it");
        }
        
        // Validate the player has enough prayer points to double
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        if ($player_prayer < self::GLOBAL_DISASTER_DOUBLE_COST) {
            throw new \BgaUserException("You need at least " . self::GLOBAL_DISASTER_DOUBLE_COST . " prayer points to double this disaster");
        }
        
        // Deduct the double cost immediately
        $this->DbQuery("UPDATE player SET player_prayer = player_prayer - " . self::GLOBAL_DISASTER_DOUBLE_COST . " WHERE player_id = $player_id");
        
        // Record the player's choice to double the effect
        $this->setGlobalDisasterChoice($card_id, $player_id, 'double', self::GLOBAL_DISASTER_DOUBLE_COST);
        
        // Track statistics: global disaster doubled
        $this->incStat(1, 'global_disasters_doubled', $player_id);
        
        // Notify about the choice and cost
        $this->notifyAllPlayers('globalDisasterChoice',
            clienttranslate('${player_name} spends ${cost} prayer points to double their global disaster (everyone will be affected)'), [
                'player_id' => $player_id,
                'player_name' => $this->getCurrentPlayerName(),
                'choice' => 'double',
                'cost' => self::GLOBAL_DISASTER_DOUBLE_COST,
                'card_id' => $card_id,
                'new_prayer_total' => $player_prayer - self::GLOBAL_DISASTER_DOUBLE_COST
            ]);

        // Clear the stored card ID and transition to card resolution
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Global disaster choice made, proceed to resolve the card
        $this->gamestate->nextState("resolveCards");
    }

    public function actNormalGlobal(): void
    {
        $this->checkAction('actNormalGlobal');
        $player_id = (int)$this->getCurrentPlayerId();
        $card_id = (int)$this->getGameStateValue('current_global_disaster');
        
        if ($card_id <= 0) {
            throw new \BgaUserException("No global disaster card to make choice for");
        }
        
        // Verify this player played the global disaster card
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        if ($card_play_info['played_by'] != $player_id) {
            throw new \BgaUserException("Only the player who played this global disaster can choose its effect");
        }
        
        // Record the player's choice for normal effect (no cost)
        $this->setGlobalDisasterChoice($card_id, $player_id, 'normal', 0);
        
        // Notify about the choice
        $this->notifyAllPlayers('globalDisasterChoice',
            clienttranslate('${player_name} chooses normal effects for their global disaster'), [
                'player_id' => $player_id,
                'player_name' => $this->getCurrentPlayerName(),
                'choice' => 'normal',
                'cost' => 0,
                'card_id' => $card_id
            ]);

        // Clear the stored card ID and transition to card resolution
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Global disaster choice made, proceed to resolve the card
        $this->gamestate->nextState("resolveCards");
    }
    
    /***************************************/

    /******** Resolve card actions ********/
    public function actSelectPlayer(int $player_id): void
    {
        // Check if it's the active player's turn
        $this->checkAction('actSelectPlayer');
        
        // Get the currently resolving card
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }
        
        // Validate the target player exists and is not the same as the player who played the card
        $all_players = $this->loadPlayersBasicInfos();
        if (!isset($all_players[$player_id])) {
            throw new \BgaUserException("Invalid target player");
        }
        
        $card_play_info = $this->getCardWithPlayInfo((int)$resolving_card['id']);
        $played_by = $card_play_info['played_by'];
        
        if ($player_id == $played_by) {
            throw new \BgaUserException("You cannot target yourself with a local disaster");
        }
        
        // Store the target player in the card record
        $this->updateCardTarget((int)$resolving_card['id'], $player_id);
        
        $card_name = $this->getCardName($resolving_card);
        $target_name = $all_players[$player_id]['player_name'];
        
        // Notify all players about the target selection
        $this->notifyAllPlayers("targetSelected", 
            clienttranslate('${card_name} will target ${target_name}'), 
            [
                'card_name' => $card_name,
                'target_name' => $target_name,
                'target_player_id' => $player_id,
                'card_id' => $resolving_card['id']
            ]
        );
        
        // Continue with card resolution via proper state transition
        $this->gamestate->nextState('beginAllPlay');
    }

    public function actAmuletChoose(bool $use_amulet): void
    {
        // Check if it's the active player's turn
        $this->checkAction('actAmuletChoose');
        $player_id = (int)$this->getCurrentPlayerId();
        
        // Start transaction for database safety
        $this->DbQuery("START TRANSACTION");
        
        try {
            // Verify player has an amulet
            $amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $player_id");
            if ($amulet_count <= 0) {
                throw new \BgaUserException("You don't have any amulets to use");
            }

            $player_name = $this->getPlayerNameById($player_id);
            
            if ($use_amulet) {
                // Player chooses to use their amulet
                $this->DbQuery("UPDATE player SET player_amulet = player_amulet - 1 WHERE player_id = $player_id");
                
                // Track statistics: amulet used
                $this->incStat(1, 'amulets_used', $player_id);
            
            $this->notifyAllPlayers("amuletUsed", 
                clienttranslate('${player_name} uses an amulet to avoid the disaster effects'), 
                [
                    'player_name' => $player_name,
                    'player_id' => $player_id,
                    'preserve' => 1500 // Show message for 1.5 seconds
                ]
            );
            
            // Store that this player used an amulet for this card resolution
            $resolving_card = $this->getCardOnTop('resolving');
            if ($resolving_card) {
                // We'll track amulet usage in a different way when applying effects
                $this->playerUsedAmulet[$player_id] = true;
            }
        } else {
            // Player chooses not to use their amulet
            $this->notifyAllPlayers("amuletNotUsed", 
                clienttranslate('${player_name} chooses not to use an amulet'), 
                [
                    'player_name' => $player_name,
                    'player_id' => $player_id,
                    'preserve' => 1500 // Show message for 1.5 seconds
                ]
            );
        }

            // Mark this player as having made their choice
            $this->gamestate->setPlayerNonMultiactive($player_id, 'beginAllPlay');
            
            // Commit the transaction
            $this->DbQuery("COMMIT");
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            $this->DbQuery("ROLLBACK");
            throw $e;
        }
        
        // BGA framework will automatically transition to 'beginAllPlay' when all multiactive players are done
    }

    public function stRollDice(): void
    {
        // Get the currently resolving card to determine which players need to roll
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        $card_type = (int)$resolving_card['type'];
        $card_type_arg = (int)$resolving_card['type_arg'];
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$card_type}, arg {$card_type_arg}");
        }

        $players_who_roll = [];
        
        // Determine which players need to roll based on card type and effects
        if ($card_type === CardType::GlobalDisaster->value) {
            // For global disasters, all players roll (subject to their choices)
            $all_players = $this->loadPlayersBasicInfos();
            $players_who_roll = array_keys($all_players);
        } else if ($card_type === CardType::LocalDisaster->value) {
            // For local disasters, only the target player rolls
            $card_play_info = $this->getCardWithPlayInfo((int)$resolving_card['id']);
            $target_player = $card_play_info['target_player'] ?? null;
            if ($target_player !== null) {
                $players_who_roll = [$target_player];
            }
        } else {
            // For bonus cards, the player who played it rolls
            $card_play_info = $this->getCardWithPlayInfo((int)$resolving_card['id']);
            $played_by = $card_play_info['played_by'] ?? null;
            if ($played_by !== null) {
                $players_who_roll = [$played_by];
            }
        }

        if (empty($players_who_roll)) {
            // No players need to roll, continue with normal effect resolution
            $this->applyBasicCardEffectsWithAmulets($resolving_card, $effects);
            $this->moveCardToResolved($resolving_card);
            $this->gamestate->nextState('beginAllPlay');
            return;
        }

        // Set players who need to roll as active
        $this->gamestate->setPlayersMultiactive($players_who_roll, 'beginAllPlay');
        
        // Determine what type of effect they're rolling for
        $roll_type = '';
        if ($effects['happiness_effect'] === "roll_d6") {
            $roll_type = 'happiness';
        } else if ($effects['prayer_effect'] === "roll_d6") {
            $roll_type = 'prayer';
        } else if ($effects['convert_to_religion'] === "roll_d6") {
            $roll_type = 'convert_to_religion';
        }

        $card_name = $this->getCardName($resolving_card);
        $this->notifyAllPlayers("diceRollRequired", 
            clienttranslate('Players must roll dice to determine ${card_name} effects'), 
            [
                'card_name' => $card_name,
                'roll_type' => $roll_type,
                'players_rolling' => $players_who_roll
            ]
        );
    }

    private function applyBasicCardEffectsWithAmulets(array $card, array $effects): void
    {
        $card_id = (int)$card['id'];
        $card_type = (int)$card['type'];
        
        // Get the full card information including who played it and who it targets
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        $played_by = $card_play_info['played_by'] ? (int)$card_play_info['played_by'] : null;
        $target_player = $card_play_info['target_player'] ? (int)$card_play_info['target_player'] : null;
        
        // Handle global disasters with player choices (amulets don't affect these as they have individual choices)
        if ($card_type === CardType::GlobalDisaster->value) {
            $this->applyGlobalDisasterEffects($card_id, $effects, $played_by);
        } else {
            // Handle local disasters and bonus cards with amulet consideration
            $this->applyTargetedCardEffectsWithAmulets($card_id, $effects, $played_by, $target_player);
        }
        

    }

    private function applyTargetedCardEffectsWithAmulets(int $card_id, array $effects, ?int $played_by, ?int $target_player): void
    {
        if ($target_player !== null) {
            // Check if the target player used an amulet
            $used_amulet = isset($this->playerUsedAmulet[$target_player]) && $this->playerUsedAmulet[$target_player];
            
            if ($used_amulet) {
                $this->notifyAllPlayers("message", 
                    clienttranslate('${player_name} is protected from the disaster effects by an amulet'), 
                    ['player_name' => $this->getPlayerNameById($target_player)]
                );
                // Skip applying harmful effects
            } else {
                // Apply effects normally to the target
                $this->applyEffectsToPlayer($target_player, $effects, 1.0, 'normal');
            }
        } else {
            // Apply effects to the player who played the card (for bonus cards)
            if ($played_by !== null) {
                $this->applyEffectsToPlayer($played_by, $effects, 1.0, 'normal');
            }
        }
    }

    public function actRollDie(): void
    {
        // Check if it's the active player's turn
        $this->checkAction('actRollDie');
        $player_id = (int)$this->getCurrentPlayerId();
        
        // Generate a random dice result using BGA framework (1-6)
        $result = bga_rand(1, 6);

        $player_name = $this->getPlayerNameById($player_id);
        
        // Get the currently resolving card
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        // Store the dice result for this player and card
        $this->storeDiceResult($player_id, (int)$resolving_card['id'], $result);

        $this->notifyAllPlayers("diceRolled", 
            clienttranslate('${player_name} rolled ${result}'), 
            [
                'player_name' => $player_name,
                'player_id' => $player_id,
                'result' => $result,
                'card_id' => $resolving_card['id']
            ]
        );

        // Mark this player as having completed their dice roll
        $this->gamestate->setPlayerNonMultiactive($player_id, 'beginAllPlay');
        
        // BGA framework will automatically transition to 'beginAllPlay' when all multiactive players are done
    }

    private function storeDiceResult(int $player_id, int $card_id, int $result): void
    {
        // Store dice result in the database for persistence
        $this->DbQuery("UPDATE player SET player_die = $result WHERE player_id = $player_id");
        
        // Also update the temporary property for immediate use in this request
        if (!isset($this->diceResults)) {
            $this->diceResults = [];
        }
        $this->diceResults[$player_id] = $result;
    }

    private function applyBasicCardEffectsWithDiceAndAmulets(array $card, array $effects): void
    {
        $card_id = (int)$card['id'];
        $card_type = (int)$card['type'];
        
        // Get the full card information including who played it and who it targets
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        $played_by = $card_play_info['played_by'] ?? null;
        $target_player = $card_play_info['target_player'] ?? null;
        
        // Replace "roll_d6" placeholders with actual dice results for each player
        $players_to_affect = [];
        
        if ($card_type === CardType::GlobalDisaster->value) {
            // Global disasters affect all players
            $all_players = $this->loadPlayersBasicInfos();
            $players_to_affect = array_keys($all_players);
        } else if ($card_type === CardType::LocalDisaster->value && $target_player !== null) {
            // Local disasters affect only the target player
            $players_to_affect = [$target_player];
        } else if ($played_by !== null) {
            // Bonus cards affect the player who played them
            $players_to_affect = [$played_by];
        }

        foreach ($players_to_affect as $player_id) {
            // Skip players who used amulets (for harmful effects)
            $used_amulet = isset($this->playerUsedAmulet[$player_id]) && $this->playerUsedAmulet[$player_id];
            
            // Create personalized effects for this player
            $player_effects = $effects;
            
            // Replace "roll_d6" with actual dice result for this player
            $dice_result = (int)$this->getUniqueValueFromDb("SELECT player_die FROM player WHERE player_id = $player_id");
            if ($dice_result === 0) {
                $dice_result = 1; // Default to 1 if no result stored
            }
            
            if ($player_effects['happiness_effect'] === "roll_d6") {
                $player_effects['happiness_effect'] = $dice_result;
            }
            if ($player_effects['prayer_effect'] === "roll_d6") {
                $player_effects['prayer_effect'] = $dice_result;
            }
            if ($player_effects['convert_to_religion'] === "roll_d6") {
                $player_effects['convert_to_religion'] = $dice_result;
            }
            
            // Apply effects to this player considering amulet usage
            $this->applyEffectsToPlayerWithAmulet($player_id, $player_effects, $used_amulet);
        }
    }

    private function applyEffectsToPlayerWithAmulet(int $player_id, array $effects, bool $used_amulet): void
    {
        // If player used an amulet, protect them from harmful effects
        if ($used_amulet) {
            // Check if there were any harmful effects that will be blocked
            // Only family_dies and convert_to_atheist are the actual harmful effects that amulets protect against
            $had_harmful_effects = (isset($effects['family_dies']) && $effects['family_dies'] > 0) ||
                                  (isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0);
            
            // Remove only the actual harmful effects that amulets protect against
            $effects['family_dies'] = 0;
            $effects['convert_to_atheist'] = 0;
            
            // Notify about amulet protection if harmful effects were blocked
            if ($had_harmful_effects) {
                $this->notifyAllPlayers("amuletProtection", 
                    clienttranslate('${player_name}\'s amulet protects them from harmful effects'), 
                    [
                        'player_name' => $this->getPlayerNameById($player_id),
                        'player_id' => $player_id,
                        'preserve' => 2000 // Show message for 2 seconds
                    ]
                );
            }
            
            // Keep ALL other effects (happiness_effect, prayer_effect, convert_to_religion, etc.)
            // Prayer effects are NOT blocked by amulets
        }
        
        // Apply the (potentially modified) effects to the player
        $this->applyEffectsToPlayer($player_id, $effects, 1.0, 'normal');
    }

    public function actDiscard(int $card_id): void
    {
        $this->checkAction('actDiscard');
        $player_id = $this->getCurrentPlayerId();
        
        // Validate that the player owns this card
        $card = $this->getObjectFromDB("
            SELECT card_id, card_type, card_type_arg, card_location, card_location_arg 
            FROM disaster_card 
            WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
            UNION
            SELECT card_id, card_type, card_type_arg, card_location, card_location_arg 
            FROM bonus_card 
            WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
        ");
        
        if (!$card) {
            throw new \BgaVisibleSystemException("Invalid card or card not in your hand");
        }
        
        // Move card to discard pile
        $card_type = (int)$card['card_type'];
        if ($card_type === CardType::GlobalDisaster->value || $card_type === CardType::LocalDisaster->value) {
            $this->DbQuery("UPDATE disaster_card SET card_location = 'discard', card_location_arg = 0 WHERE card_id = $card_id");
        } else {
            $this->DbQuery("UPDATE bonus_card SET card_location = 'discard', card_location_arg = 0 WHERE card_id = $card_id");
        }
        
        // Notify all players about the discard
        $this->notifyAllPlayers('cardDiscarded', clienttranslate('${player_name} discarded a card'), [
            'player_id' => $player_id,
            'player_name' => $this->getPlayerNameById($player_id),
            'card_id' => $card_id,
            'card_type' => $card['card_type'],
            'card_type_arg' => $card['card_type_arg']
        ]);
        
        // Mark this player as having completed their discard
        $this->gamestate->setPlayerNonMultiactive($player_id, 'beginAllPlay');
    }

    public function stDiscard(): void
    {
        // All players must discard a card when riots is played
        $all_players = $this->loadPlayersBasicInfos();
        $players_with_cards = [];
        $players_without_cards = [];
        
        // Check which players have cards in hand
        foreach ($all_players as $player_id => $player) {
            $card_count = $this->getCollectionFromDb("
                SELECT card_id FROM disaster_card WHERE card_location = 'hand' AND card_location_arg = $player_id
                UNION
                SELECT card_id FROM bonus_card WHERE card_location = 'hand' AND card_location_arg = $player_id
            ");
            
            if (count($card_count) > 0) {
                $players_with_cards[] = $player_id;
            } else {
                $players_without_cards[] = $player_id;
            }
        }
        
        // Notify players without cards that they are waiting
        if (!empty($players_without_cards)) {
            $players_without_names = [];
            foreach ($players_without_cards as $player_id) {
                $players_without_names[] = $all_players[$player_id]['player_name'];
            }
            $waiting_names = implode(', ', $players_without_names);
            
            $this->notifyAllPlayers('message', 
                count($players_without_cards) == 1 ? 
                    clienttranslate('${waiting_names} has no cards and is waiting for others to discard') :
                    clienttranslate('${waiting_names} have no cards and are waiting for others to discard'), 
                [
                    'waiting_names' => $waiting_names,
                    'preserve' => 3000
                ]
            );
        }
        
        if (empty($players_with_cards)) {
            // No players have cards to discard, move to next phase
            $this->notifyAllPlayers('message', 
                clienttranslate('No players have cards to discard'), 
                [
                    'preserve' => 2000
                ]
            );
            $this->gamestate->nextState('beginAllPlay');
            return;
        }
        
        // Set all players with cards as active for discard phase
        $this->gamestate->setPlayersMultiactive($players_with_cards, 'beginAllPlay');
    }

    public function actSelectTarget(int $target_player_id): void
    {
        $this->checkAction('actSelectTarget');
        $current_player_id = $this->getCurrentPlayerId();
        
        // Get the card being resolved from global variables
        $card_id = $this->getGameStateValue('card_being_resolved');
        if (!$card_id) {
            throw new \BgaVisibleSystemException("No card being resolved");
        }
        
        // Get the card details to retrieve its effects
        $card = $this->getObjectFromDB("
            SELECT card_type, card_type_arg 
            FROM disaster_card 
            WHERE card_id = $card_id
            UNION
            SELECT card_type, card_type_arg 
            FROM bonus_card 
            WHERE card_id = $card_id
        ");
        
        if (!$card) {
            throw new \BgaVisibleSystemException("Card not found");
        }
        
        // Get the card's effects using the existing method
        $card_effects = $this->getCardEffects((int)$card['card_type'], (int)$card['card_type_arg']);
        
        // Validate the target has temples
        $target_data = $this->getObjectFromDB("SELECT player_temple FROM player WHERE player_id = $target_player_id");
        if (!$target_data || $target_data['player_temple'] <= 0) {
            throw new \BgaVisibleSystemException("Target player has no temples to destroy");
        }
        
        // Store the selected target
        $this->setGameStateValue('selected_target_player', $target_player_id);
        
        // Apply the temple destruction effects to the selected target
        $this->applyEffectsToPlayer($target_player_id, $card_effects, 1.0, 'normal');
        
        // Continue to next state
        $this->gamestate->nextState('continueResolve');
    }

    /******************************/

    /**
     * Get all players who currently have temples
     * @return array Array of player IDs who have temples
     */
    private function getPlayersWithTemples(): array
    {
        $players_with_temples = $this->getCollectionFromDb(
            "SELECT player_id FROM player WHERE player_temple > 0"
        );
        return array_keys($players_with_temples);
    }

    /***** helpers ******/

    /**
     * Set the play order for a card to enable FIFO resolution
     * @param int $card_id The card ID to set play order for
     * @param int $played_by The player ID who played this card
     * @param int|null $target_player The player ID this card targets (null for self/global effects)
     */
    private function setCardPlayOrder(int $card_id, int $played_by, ?int $target_player = null): void
    {
        // Get the next play order number atomically to prevent race conditions
        $next_order_query = "
            SELECT COALESCE(MAX(play_order), 0) + 1 as next_order 
            FROM (
                SELECT play_order FROM disaster_card WHERE card_location = 'played'
                UNION ALL
                SELECT play_order FROM bonus_card WHERE card_location = 'played'
            ) AS combined_orders
        ";
        $next_order = (int)$this->getUniqueValueFromDb($next_order_query);
        
        // Determine which table to update based on card existence
        $disaster_card = $this->disasterCards->getCard($card_id);
        if ($disaster_card !== null) {
            $target_sql = $target_player !== null ? $target_player : 'NULL';
            $this->DbQuery("UPDATE disaster_card SET play_order = $next_order, played_by = $played_by, target_player = $target_sql WHERE card_id = $card_id");
        } else {
            $target_sql = $target_player !== null ? $target_player : 'NULL';
            $this->DbQuery("UPDATE bonus_card SET play_order = $next_order, played_by = $played_by, target_player = $target_sql WHERE card_id = $card_id");
        }
    }

    /**
     * Determine the target player for a card based on its type and effects
     * @param array $card The card data
     * @param int $player_id The player who played the card
     * @return int|null The target player ID, or null for global/self effects
     */
    private function determineCardTarget(array $card, int $player_id): ?int
    {
        $card_type = (int)$card['type'];
        
        // Global disasters affect all players - no specific target
        if ($card_type === CardType::GlobalDisaster->value) {
            return null;
        }
        
        // Local disasters and bonus cards that target specific players will need target selection
        // For now, we'll mark them as needing target selection by returning null
        // The actual target will be set later when the player selects it during resolution
        if ($card_type === CardType::LocalDisaster->value) {
            // Local disasters target other players - will be selected during resolution
            return null;
        }
        
        // Bonus cards typically affect the player who played them
        if ($card_type === CardType::Bonus->value) {
            return $player_id;
        }
        
        return null;
    }

    /**
     * Update the target player for a card (used when target is selected during resolution)
     * @param int $card_id The card ID to update
     * @param int $target_player_id The target player ID
     */
    private function setCardTarget(int $card_id, int $target_player_id): void
    {
        // Determine which table to update based on card existence
        $disaster_card = $this->disasterCards->getCard($card_id);
        if ($disaster_card !== null) {
            $this->DbQuery("UPDATE disaster_card SET target_player = $target_player_id WHERE card_id = $card_id");
        } else {
            $this->DbQuery("UPDATE bonus_card SET target_player = $target_player_id WHERE card_id = $card_id");
        }
    }

    /**
     * Get card information including who played it and who it targets
     * @param int $card_id The card ID to get info for
     * @return array|null Card data with played_by and target_player fields
     */
    private function getCardWithPlayInfo(int $card_id): ?array
    {
        // Try disaster cards first - use aliases to match expected field names
        $disaster_card = $this->getObjectFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, play_order, played_by, target_player FROM disaster_card WHERE card_id = $card_id");
        if ($disaster_card !== null) {
            return $disaster_card;
        }
        
        // Try bonus cards - use aliases to match expected field names
        $bonus_card = $this->getObjectFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, play_order, played_by, target_player FROM bonus_card WHERE card_id = $card_id");
        return $bonus_card;
    }

    /**
     * Update the target player for a card
     * @param int $card_id The card ID to update
     * @param int $target_player_id The player being targeted
     */
    private function updateCardTarget(int $card_id, int $target_player_id): void
    {
        // Try to update disaster card first
        $disaster_updated = $this->DbQuery("UPDATE disaster_card SET target_player = $target_player_id WHERE card_id = $card_id");
        
        // If no disaster card was updated, try bonus card
        if ($this->DbAffectedRow() == 0) {
            $this->DbQuery("UPDATE bonus_card SET target_player = $target_player_id WHERE card_id = $card_id");
        }
    }

    /**
     * Initialize global disaster choice for the player who played the card
     * @param int $card_id The global disaster card ID
     * @param int $player_id The player who played the card
     */
    private function initializeGlobalDisasterChoice(int $card_id, int $player_id): void
    {
        $this->DbQuery("INSERT INTO global_disaster_choice (card_id, player_id, choice, cost_paid) 
                       VALUES ($card_id, $player_id, 'normal', 0)");
    }

    /**
     * Set a player's choice for a global disaster
     * @param int $card_id The global disaster card ID
     * @param int $player_id The player making the choice
     * @param string $choice 'normal', 'avoid', or 'double'
     * @param int $cost_paid Prayer points spent (for avoid/double choice)
     */
    private function setGlobalDisasterChoice(int $card_id, int $player_id, string $choice, int $cost_paid = 0): void
    {
        $this->DbQuery("UPDATE global_disaster_choice 
                       SET choice = '$choice', cost_paid = $cost_paid 
                       WHERE card_id = $card_id AND player_id = $player_id");
    }

    /**
     * Get a player's choice for a global disaster
     * @param int $card_id The global disaster card ID
     * @param int $player_id The player ID
     * @return array|null Choice data with choice and resources_spent
     */
    private function getGlobalDisasterChoice(int $card_id, int $player_id): ?array
    {
        return $this->getObjectFromDb("SELECT * FROM global_disaster_choice 
                                      WHERE card_id = $card_id AND player_id = $player_id");
    }

    /**
     * Get all players' choices for a global disaster
     * @param int $card_id The global disaster card ID
     * @return array Array of player choices indexed by player_id
     */
    /**
     * Clear global disaster choices for a card (when resolution is complete)
     * @param int $card_id The global disaster card ID
     */
    private function clearGlobalDisasterChoices(int $card_id): void
    {
        $this->DbQuery("DELETE FROM global_disaster_choice WHERE card_id = $card_id");
    }

    /**
     * Reset play orders when starting a new round (optional cleanup)
     */
    private function resetPlayOrders(): void
    {
        $this->DbQuery("UPDATE disaster_card SET play_order = NULL WHERE card_location != 'played'");
        $this->DbQuery("UPDATE bonus_card SET play_order = NULL WHERE card_location != 'played'");
    }

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

        return $leader_int == 1;
    }

    /**
     * Check if any player has temples (for Temple Destroyed card validation)
     */
    private function anyPlayerHasTemples(): bool
    {
        $temple_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_temple) FROM player WHERE player_eliminated = 0");
        return $temple_count > 0;
    }

    /**
     * Check if there are any temple-related cards in play/resolving that could be targets
     */
    private function hasTempleCardsInPlay(): bool
    {
        // Check for Temple bonus cards in played or resolving locations
        $played_temple_cards = $this->bonusCards->getCardsInLocation('played');
        $resolving_temple_cards = $this->bonusCards->getCardsInLocation('resolving');
        $all_temple_cards = array_merge($played_temple_cards, $resolving_temple_cards);
        
        foreach ($all_temple_cards as $card) {
            if ((int)$card['type_arg'] === BonusCard::Temple->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate if a card would have any effect when played
     * Throws BgaUserException with warning if card would be ineffective
     */
    private function validateCardEffectiveness(array $card, int $player_id): void
    {
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        
        // Check Recover Leader cards when player already has a leader
        if ($card_type === CardType::Bonus->value && $card_type_arg === BonusCard::NewLeader->value) {
            $player_has_leader = (int)$this->getUniqueValueFromDb("SELECT player_chief FROM player WHERE player_id = $player_id");
            if ($player_has_leader > 0) {
                throw new \BgaUserException(clienttranslate("Warning: You already have a leader. This Recover Leader card will have no effect."));
            }
        }
        
        // Check Temple Destroyed cards when no temples exist
        if ($card_type === CardType::LocalDisaster->value && $card_type_arg === LocalDisasterCard::TempleDestroyed->value) {
            if (!$this->anyPlayerHasTemples() && !$this->hasTempleCardsInPlay()) {
                throw new \BgaUserException(clienttranslate("Warning: No player has temples and no temple cards are in play. This Temple Destroyed card will have no effect."));
            }
        }
        
        // Check Convert Atheist action when no atheists are available
        $atheist_count = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        
        // Check Convert Believer actions when no other players have families
        $other_players_families = (int)$this->getUniqueValueFromDb("SELECT SUM(player_family) FROM player WHERE player_id != $player_id AND player_eliminated = 0");
        
        // Additional warning for cards with convert_to_atheist effects when all players have 0 families
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects !== null) {
            // Check for convert to atheist effects when target players have no families
            if (isset($effects['convert_to_atheist']) && $effects['convert_to_atheist'] > 0) {
                if ($card_type === CardType::LocalDisaster->value) {
                    // For local disasters, we don't know the target yet, so don't warn
                } elseif ($card_type === CardType::GlobalDisaster->value) {
                    // For global disasters, check if any non-eliminated player has families
                    $total_families = (int)$this->getUniqueValueFromDb("SELECT SUM(player_family) FROM player WHERE player_eliminated = 0");
                    if ($total_families === 0) {
                        throw new \BgaUserException(clienttranslate("Warning: No players have families. The convert to atheist effect will have no impact."));
                    }
                }
            }
            
            // Check for family death effects when target players have no families
            if (isset($effects['family_dies']) && $effects['family_dies'] > 0) {
                if ($card_type === CardType::LocalDisaster->value) {
                    // For local disasters, we don't know the target yet, so don't warn
                } elseif ($card_type === CardType::GlobalDisaster->value) {
                    $total_families = (int)$this->getUniqueValueFromDb("SELECT SUM(player_family) FROM player WHERE player_eliminated = 0");
                    if ($total_families === 0) {
                        throw new \BgaUserException(clienttranslate("Warning: No players have families. The family death effect will have no impact."));
                    }
                }
            }
        }
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
        
        // Use the same effects system as card resolution to ensure consistency
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects !== null && isset($effects['prayer_cost'])) {
            return (int)$effects['prayer_cost'];
        }
        
        return 0; // Default to 0 if not found
    }

    public function canPlayCard(int $player_id, ?array $card): bool
    {
        if ($card === null) {
            return false;
        }
        
        $player_prayer = (int)$this->getUniqueValueFromDb("SELECT player_prayer FROM player WHERE player_id = $player_id");
        $prayer_cost = $this->getCardPrayerCost($card);
        
        // Check if player has enough prayer points
        return $player_prayer >= $prayer_cost;
    }

    /**
     * Validate card can be played and show warnings for ineffective plays
     * This is called when player attempts to play a card
     */
    public function validateCardPlay(int $player_id, array $card): void
    {
        // First check basic requirements (prayer cost)
        if (!$this->canPlayCard($player_id, $card)) {
            $card_name = $this->getCardName($card);
            throw new \BgaUserException("You don't have enough prayer points to play $card_name");
        }
        
        // Then check if card would be effective
        $this->validateCardEffectiveness($card, $player_id);
    }


    /******* Arg functions ************/
    public function argSelectTarget(): array
    {
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            return ['available_targets' => []];
        }

        $card_type = (int)$resolving_card['type'];
        $card_type_arg = (int)$resolving_card['type_arg'];
        $available_targets = [];

        // Special handling for Temple Destroyed card
        if ($card_type === CardType::LocalDisaster->value && $card_type_arg === LocalDisasterCard::TempleDestroyed->value) {
            $players_with_temples = $this->getPlayersWithTemples();
            if (empty($players_with_temples)) {
                // No players have temples, skip target selection
                return [
                    'available_targets' => [],
                    'skip_target_selection' => true,
                    'skip_message' => clienttranslate('No players have temples to destroy')
                ];
            }
            $available_targets = $players_with_temples;
        } else {
            // Default targeting: all other players
            $available_targets = $this->getAvailableTargets();
        }

        return [
            'available_targets' => $available_targets,
            'card_id' => $resolving_card['id'],
            'card_name' => $this->getCardName($resolving_card)
        ];
    }

    public function argActivateLeader() : array
    {
        return [
            '_no_notify' => !$this->check_playerHasLeader(),
        ];
    }
    
    public function argPlayCard() : array
    {
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        $round_leader_played_card = $this->getGameStateValue("round_leader_played_card");
        
        // Determine available actions based on player role and state
        $is_round_leader = ($current_player == $round_leader);
        $can_convert = $is_round_leader && ($round_leader_played_card == 0);
        $can_pass = true; // All players can always pass
        
        return [
            'is_round_leader' => $is_round_leader,
            'can_convert' => $can_convert,
            'can_pass' => $can_pass,
            'round_leader_played_card' => $round_leader_played_card
        ];
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
        // Get total number of players at game start
        $starting_players = $this->getPlayersNumber();
        
        // Get number of eliminated players
        $eliminated_count = (int)$this->getUniqueValueFromDb("SELECT SUM(player_eliminated) FROM player");
        
        // Calculate remaining players
        $remaining_players = $starting_players - $eliminated_count;
        
        // Game ends when only 1 player remains
        if ($remaining_players <= 1) {
            return 100; // Game is over or about to end
        }
        
        // Calculate progression based on players eliminated vs. players that need to be eliminated
        // We need to eliminate (starting_players - 1) players to end the game
        $players_to_eliminate = $starting_players - 1;
        
        // Progression = (players_eliminated / players_that_need_to_be_eliminated) * 100
        $progression = ($eliminated_count / $players_to_eliminate) * 100;
        
        return (int)min(100, max(0, $progression));
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
                player_card_count cards,
                player_color color
                FROM player"
        );
        /* add name and card type counts */
        foreach ($result["players"] as &$player)
        {
            $player["name"] = $this->getPlayerNameById($player["id"]);
            
            // Add disaster and bonus card counts for all players (visible information)
            $player["disaster_cards"] = (int)$this->disasterCards->countCardInLocation("hand", $player["id"]);
            $player["bonus_cards"] = (int)$this->bonusCards->countCardInLocation("hand", $player["id"]);
        }

        // Fetch the number of atheist families from the database
        $atheistCount = (int)$this->getUniqueValueFromDb("SELECT global_value FROM global WHERE global_id = 101");
        $result["atheist_families"] = $atheistCount;

        // Add round leader information
        $result["round_leader"] = $this->getGameStateValue("roundLeader");
        $result["round_leader_played_card"] = $this->getGameStateValue("round_leader_played_card");

        // // Fetch the dice information from the database
        // $result["dices"] = $this->getCollectionFromDb(
        //     "SELECT `dice_id` `id`, `dice_value` `value` FROM `dice`"
        // );

        /* Get all cards this player has and where it is */
        $result["handDisaster"] = $this->disasterCards->getPlayerHand($current_player_id);
        $result["handBonus"] = $this->bonusCards->getPlayerHand($current_player_id);

        /* Get played and resolved cards for all players to display in the common areas */
        // Use custom queries to include played_by information
        $result["playedDisaster"] = $this->getCollectionFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, played_by FROM disaster_card WHERE card_location = 'played'");
        $result["playedBonus"] = $this->getCollectionFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, played_by FROM bonus_card WHERE card_location = 'played'");
        $result["resolvedDisaster"] = $this->getCollectionFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, played_by FROM disaster_card WHERE card_location = 'resolved'");
        $result["resolvedBonus"] = $this->getCollectionFromDb("SELECT card_id as id, card_type as type, card_type_arg as type_arg, card_location as location, card_location_arg as location_arg, played_by FROM bonus_card WHERE card_location = 'resolved'");

        /* TODO get size of each players hand */

        // Add game options to frontend data
        $result["game_options"] = $this->getGameOptions();

        return $result;
    }

    /**
     * Get game options for frontend
     */
    protected function getGameOptions(): array
    {
        return [
            '100' => $this->tableOptions->get(100), // Quickstart Cards
            '101' => $this->tableOptions->get(101), // Show End-Round Predictions
        ];
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
        // Order: blue, red, yellow, green, purple
        $player_color_list = ["#4685FF", "#C22D2D", "#C8CA25", "#2EA232", "#913CB3"];
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
        $this->setGameStateInitialValue("roundLeader", 0);
        $this->setGameStateInitialValue("saved_state", 0);
        $this->setGameStateInitialValue("saved_active_player", 0);
        $this->setGameStateInitialValue("current_global_disaster", 0);
        $this->setGameStateInitialValue("round_leader_played_card", 0);
        $this->setGameStateInitialValue("round_leader_continuing_play", 0);
        $this->setGameStateInitialValue("discard_completed_for_card", 0);
        $this->setGameStateInitialValue("dice_completed_for_card", 0);
        $this->setGameStateInitialValue("convert_pray_requested", 0);
        $this->setGameStateInitialValue("round_leader_passed_this_cycle", 0);

        $disasterCards = array(

            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Tornado->value,        'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Earthquake->value,     'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::BadWeather->value,     'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::Locust->value,         'nbr' => 4),
            array( 'type' => CardType::LocalDisaster->value,  'type_arg' => LocalDisasterCard::TempleDestroyed->value,'nbr' => 3),

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


        );
        $this->disasterCards->createCards($disasterCards, 'deck');
        $this->disasterCards->shuffle('deck');

        // Ensure bonus card table auto-increment starts at 100 to avoid ID conflicts
        $this->DbQuery("ALTER TABLE bonus_card AUTO_INCREMENT = 100");

        $bonusCards = array(
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::GoodWeather->value,           'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::DoubleHarvest->value,         'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Fertility->value,             'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Festivities->value,           'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::NewLeader->value,             'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Temple->value,                'nbr' => 3),
            array( 'type' => CardType::Bonus->value,           'type_arg' => BonusCard::Amulets->value,               'nbr' => 3),
        );
        $this->bonusCards->createCards($bonusCards, 'deck', 100); // Start bonus card IDs at 100
        $this->bonusCards->shuffle('deck');

        // Initialize meeples for each player (families and chief)
        foreach ($players as $player_id => $player) {
            // Example: 5 families and 1 chief per player
            self::DbQuery("UPDATE player SET player_family=5, player_chief=1, player_happiness=5 WHERE player_id=$player_id");
        }

        // Initialize atheist families (e.g., 3 per player, stored in a global table or variable)
        $atheist_start = count($players) * 3;

        // Init game statistics.

        // NOTE: statistics used in this file must be defined in your `stats.json` file.

        // Initialize table statistics
        $this->initStat("table", "total_rounds", 0);
        $this->initStat("table", "total_global_disasters", 0);
        $this->initStat("table", "total_local_disasters", 0);
        $this->initStat("table", "total_bonus_cards", 0);
        $this->initStat("table", "players_eliminated", 0);

        // Initialize player statistics for all players
        $this->initStat("player", "atheists_converted", 0);
        $this->initStat("player", "believers_converted", 0);
        $this->initStat("player", "families_lost", 0);
        $this->initStat("player", "families_died", 0);
        $this->initStat("player", "families_became_atheist", 0);
        $this->initStat("player", "temples_built", 0);
        $this->initStat("player", "temples_destroyed", 0);
        $this->initStat("player", "amulets_gained", 0);
        $this->initStat("player", "amulets_used", 0);
        $this->initStat("player", "speeches_given", 0);
        $this->initStat("player", "leader_sacrificed", 0);
        $this->initStat("player", "cards_played", 0);
        $this->initStat("player", "global_disasters_doubled", 0);
        $this->initStat("player", "global_disasters_avoided", 0);

        // Initialize global atheist families pool
        $player_count = count($players);
        $initial_atheist_families = $player_count * 3; // 3 families per player start on the Kalua board
        
        // Use direct database insert for custom global variable
        $this->DbQuery("INSERT INTO global (global_id, global_value) VALUES (101, $initial_atheist_families) 
                       ON DUPLICATE KEY UPDATE global_value = VALUES(global_value)");

        // TODO: Setup the initial game situation here.
        $initial_leader = $this->activeNextPlayer();
        $this->setGameStateValue("roundLeader", $initial_leader);
        
        // Notify about initial round leader
        $this->notifyAllPlayers('initialRoundLeader', clienttranslate('${player_name} will lead the first round'), [
            'player_id' => $initial_leader,
            'player_name' => $this->getPlayerNameById($initial_leader)
        ]);
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
        $statename = $state['name'];
        
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case "phaseOneDraw":
                    // Zombie player always draws from disaster deck
                    $this->actDrawCard(STR_CARD_TYPE_DISASTER);
                    break;
                    
                case "phaseTwoActivateLeader":
                    // Zombie player selects randomly from available options
                    $args = $this->argActivateLeader();
                    $available_actions = [];
                    
                    if (isset($args['can_sacrifice']) && $args['can_sacrifice']) $available_actions[] = 'sacrifice';
                    if (isset($args['can_convert_atheists']) && $args['can_convert_atheists']) $available_actions[] = 'convert_atheists';
                    if (isset($args['can_convert_believers']) && $args['can_convert_believers']) $available_actions[] = 'convert_believers';
                    if (isset($args['can_give_speech']) && $args['can_give_speech']) $available_actions[] = 'give_speech';
                    
                    if (!empty($available_actions)) {
                        $chosen_action = $available_actions[array_rand($available_actions)];
                        switch ($chosen_action) {
                            case 'sacrifice':
                                $this->actSacrificeLeader();
                                break;
                            case 'convert_atheists':
                                $this->actConvertAtheists();
                                break;
                            case 'convert_believers':
                                // Pick a random target for conversion
                                $targets = $this->getAvailableTargetsForConvert($active_player);
                                if (!empty($targets)) {
                                    $target_id = $targets[array_rand($targets)];
                                    $this->actConvertBelievers($target_id);
                                } else {
                                    $this->gamestate->nextState("nextPlayerTwo");
                                }
                                break;
                            case 'give_speech':
                                $this->actGiveSpeech();
                                break;
                        }
                    } else {
                        // No actions available, skip turn
                        $this->gamestate->nextState("nextPlayerTwo");
                    }
                    break;
                    
                case "phaseThreePlayCard":
                    $args = $this->argPlayCard();
                    $player_hand = $this->getPlayerHandForZombie($active_player);
                    
                    if (isset($args['can_pass']) && $args['can_pass']) {
                        // Non-round leader zombie passes
                        $this->actPlayCardPass();
                    } else {
                        // Round leader zombie must play a card
                        if (!empty($player_hand)) {
                            // Check for Destroy Temple card if another player has temples
                            $destroy_temple_card = null;
                            $other_players_have_temples = false;
                            
                            // Check if other players have temples
                            $players = $this->loadPlayersBasicInfos();
                            foreach (array_keys($players) as $player_id) {
                                if ($player_id != $active_player) {
                                    $temple_count = $this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $player_id");
                                    if ($temple_count > 0) {
                                        $other_players_have_temples = true;
                                        break;
                                    }
                                }
                            }
                            
                            // Look for Destroy Temple card
                            if ($other_players_have_temples) {
                                foreach ($player_hand as $card) {
                                    if (
                                        isset($card['type']) && isset($card['type_arg']) &&
                                        $card['type'] == CardType::LocalDisaster->value &&
                                        $card['type_arg'] == LocalDisasterCard::TempleDestroyed->value
                                    ) {
                                        $destroy_temple_card = $card;
                                        break;
                                    }
                                }
                            }
                            
                            // Play Destroy Temple if available and conditions met, otherwise play random card
                            $card_to_play = $destroy_temple_card ? $destroy_temple_card : $player_hand[array_rand($player_hand)];
                            $this->actPlayCard($card_to_play['id']);
                        } else {
                            // No cards to play, say convert
                            $this->actSayConvert();
                        }
                    }
                    break;
                    
                case "phaseThreeCheckGlobal":
                    // Zombie player randomly selects from available options
                    $args = $this->argGlobalOption();
                    $available_options = [];
                    
                    if (isset($args['can_avoid']) && $args['can_avoid']) $available_options[] = 'avoid';
                    if (isset($args['can_double']) && $args['can_double']) $available_options[] = 'double';
                    $available_options[] = 'normal'; // Always available
                    
                    $chosen_option = $available_options[array_rand($available_options)];
                    switch ($chosen_option) {
                    case 'avoid':
                        $this->actAvoidGlobal();
                        break;
                    case 'double':
                        $this->actDoubleGlobal();
                        break;
                    case 'normal':
                        $this->actNormalGlobal();
                        break;
                    }
                    break;
                    
                case "phaseThreeSelectTargets":
                    // Zombie player selects target randomly for Temple Destroyed or other targeting cards
                    $args = $this->argSelectTarget();
                    $available_targets = isset($args['possible_targets']) ? $args['possible_targets'] : [];
                    
                    if (!empty($available_targets)) {
                        $random_target = $available_targets[array_rand($available_targets)];
                        $this->actSelectTarget($random_target);
                    } else {
                        // No targets available, this shouldn't happen but handle gracefully
                        $this->gamestate->nextState("continueResolve");
                    }
                    break;
                    
                case "reflexiveBuyCard":
                    // Zombie player cancels buy card
                    $this->actCancelBuyCard();
                    break;
                    
                default:
                    throw new \BgaVisibleSystemException("Zombie handling not implemented for state: $statename");
            }
        } else if ($state['type'] === "multipleactiveplayers") {
            switch ($statename) {
                case "phaseThreeResolveAmulets":
                    // Zombie player doesn't use amulet
                    $this->actAmuletChoose(false);
                    break;
                    
                case "phaseThreeRollDice":
                    // Zombie player rolls dice automatically
                    $this->actRollDie();
                    break;
                    
                case "phaseThreeDiscard":
                    // Zombie player discards randomly
                    $player_hand = $this->getPlayerHandForZombie($active_player);
                    if (!empty($player_hand)) {
                        $random_card = array_rand($player_hand);
                        $this->actDiscard($player_hand[$random_card]['id']);
                    } else {
                        $this->gamestate->setPlayerNonMultiactive($active_player, 'beginAllPlay');
                    }
                    break;
                    
                case "initialDraw":
                    // Zombie player draws randomly during initial setup
                    $this->actDrawCardInit(STR_CARD_TYPE_DISASTER);
                    break;
                    
                default:
                    // For other multiactive states, just set player as non-active
                    $this->gamestate->setPlayerNonMultiactive($active_player, '');
                    break;
            }
        }
    }

    private function getAvailableTargets()
    {
        // For zombie mode, use the same logic as argSelectTarget to get valid targets
        try {
            $args = $this->argSelectTarget();
            return isset($args['possible_targets']) ? $args['possible_targets'] : [];
        } catch (\Exception $e) {
            // Fallback: get all other players as potential targets
            $players = $this->loadPlayersBasicInfos();
            $targets = [];
            $active_player = $this->getActivePlayerId();
            
            foreach (array_keys($players) as $player_id) {
                if ($player_id != $active_player) {
                    $targets[] = $player_id;
                }
            }
            
            return $targets;
        }
    }

    // Helper for zombie: get all cards in hand for a player
    private function getPlayerHandForZombie($player_id)
    {
        $hand = [];
        $hand = array_merge(
            $this->disasterCards->getPlayerHand($player_id),
            $this->bonusCards->getPlayerHand($player_id)
        );
        return $hand;
    }

    // Helper for zombie: get available targets for convert believers
    private function getAvailableTargetsForConvert($active_player)
    {
        $players = $this->loadPlayersBasicInfos();
        $targets = [];
        foreach (array_keys($players) as $player_id) {
            if ($player_id != $active_player) {
                $targets[] = $player_id;
            }
        }
        return $targets;
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
    private function drawCard_private(string $type, ?int $player_id = null, bool $suppressNotifications = false) : void
    {
        $card = null;
        if ($player_id === null) {
            $player_id = (int)$this->getCurrentPlayerId();
        }
        
        if ($type != STR_CARD_TYPE_DISASTER && $type != STR_CARD_TYPE_BONUS)
        {
            throw new \BgaVisibleSystemException($this->_("Unknown card type " + $type));
        }

        
        // Check deck size before drawing
        if (STR_CARD_TYPE_DISASTER == $type)
        {
            $deck_count = $this->disasterCards->countCardInLocation("deck");
            if ($deck_count == 0) {
                throw new \BgaUserException("No more disaster cards available");
            }
            $card = $this->disasterCards->pickCardForLocation("deck", "hand", $player_id);
        }
        else if (STR_CARD_TYPE_BONUS == $type)
        {           
            $deck_count = $this->bonusCards->countCardInLocation("deck");
            if ($deck_count == 0) {
                throw new \BgaUserException("No more bonus cards available");
            }
            $card = $this->bonusCards->pickCardForLocation("deck", "hand", $player_id);
        }

        if ($card === null) {
            throw new \BgaUserException("No more cards available in the $type deck");
        }

        // Always send public notification with card data (for real-time UI updates)
        $notificationData = [
            'player_id' => $player_id,
            'player_name' => $this->getPlayerNameById($player_id),
            'card_id' => $card['id'],
            'card_type' => $card['type'],
            'card_type_arg' => $card['type_arg']
        ];

        if (!$suppressNotifications) {
            // Send with text message for normal gameplay
            $this->notifyAllPlayers('playerDrewCard', clienttranslate('${player_name} drew a card'), $notificationData);
        } else {
            // Send without text message during initial draw (suppress sidebar text)
            $this->notifyAllPlayers('playerDrewCard', '', $notificationData);
        }

        // Always send private notification to the player with card details
        $this->notifyPlayer($player_id, 'cardDrawn', '', [
            'card' => $card
        ]);
    }

    /* Development Methods */
    
    /**
     * Get current game statistics for development testing
     * This method is for development/testing purposes only
     */
    public function getDevStatistics(): array
    {
        // Get table statistics
        $table_stats = [];
        $table_stats['total_rounds'] = $this->getStat('total_rounds');
        $table_stats['total_global_disasters'] = $this->getStat('total_global_disasters');
        $table_stats['total_local_disasters'] = $this->getStat('total_local_disasters');
        $table_stats['total_bonus_cards'] = $this->getStat('total_bonus_cards');
        $table_stats['players_eliminated'] = $this->getStat('players_eliminated');
        
        // Get player statistics
        $player_stats = [];
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $player_stats[$player_id] = [
                'name' => $player['player_name'],
                'atheists_converted' => $this->getStat('atheists_converted', $player_id),
                'believers_converted' => $this->getStat('believers_converted', $player_id),
                'families_lost' => $this->getStat('families_lost', $player_id),
                'families_died' => $this->getStat('families_died', $player_id),
                'families_became_atheist' => $this->getStat('families_became_atheist', $player_id),
                'temples_built' => $this->getStat('temples_built', $player_id),
                'temples_destroyed' => $this->getStat('temples_destroyed', $player_id),
                'amulets_gained' => $this->getStat('amulets_gained', $player_id),
                'amulets_used' => $this->getStat('amulets_used', $player_id),
                'speeches_given' => $this->getStat('speeches_given', $player_id),
                'leader_sacrificed' => $this->getStat('leader_sacrificed', $player_id),
                'cards_played' => $this->getStat('cards_played', $player_id),
                'global_disasters_doubled' => $this->getStat('global_disasters_doubled', $player_id),
                'global_disasters_avoided' => $this->getStat('global_disasters_avoided', $player_id),
            ];
        }
        
        return [
            'table' => $table_stats,
            'players' => $player_stats
        ];
    }
}
