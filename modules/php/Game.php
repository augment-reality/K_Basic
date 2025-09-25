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
            "round_leader_continuing_play" => 15 /* Whether round leader is continuing to play multiple cards */
        ]);          

        //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards->init( "bonus_card" );
        
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
        // Only reset round leader played card flag when it's the round leader's FRESH turn
        // (not when continuing to play cards via 'playAgain' transition)
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        $is_continuing_play = $this->getGameStateValue("round_leader_continuing_play");
        
        if ($current_player == $round_leader && !$is_continuing_play) {
            // This is a fresh turn for the round leader, reset the flag
            $this->setGameStateValue("round_leader_played_card", 0);
            
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

        // Log the beginning of the resolution phase
        $this->notifyAllPlayers("message", clienttranslate("Beginning card resolution phase..."), [
            'preserve' => 2000 // Show message for 2 seconds
        ]);

        // First check if there's a card currently resolving
        $resolving_card = $this->getCardOnTop('resolving');
        
        if ($resolving_card === null) {
            // No card currently resolving, try to get the next card from played cards
            $next_card = $this->getNextCardToResolve();
            
            if ($next_card === null) {
                // No more cards to resolve, go to next phase
                $this->notifyAllPlayers("cardResolutionComplete", 
                    clienttranslate("Card resolution phase complete"), [
                        'preserve' => 2000 // Show message for 2 seconds
                    ]
                );
                $this->gamestate->nextState('noCards');
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
        $this->resolveCardEffects($resolving_card, $this->amuletsResolved);
        
        // Notify that this card's resolution is complete
        $card_name = $this->getCardName($resolving_card);
        $this->notifyAllPlayers("cardResolved", 
            clienttranslate("${card_name} has been resolved"), [
                'card_name' => $card_name,
                'card_id' => $resolving_card['id'],
                'card_type' => $resolving_card['type'],
                'card_type_arg' => $resolving_card['type_arg'],
                'preserve' => 1500 // Show message for 1.5 seconds
            ]
        );

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
    private function resolveCardEffects(array $card, bool $amulets_resolved = false): void
    {
        // If this is a fresh card resolution (not returning from amulet/dice resolution), reset flags
        if (!$amulets_resolved) {
            $this->amuletsResolved = false;
            $this->playerUsedAmulet = [];
            $this->diceResults = [];
            $this->multiactiveAmuletPlayers = [];
        }
        
        $card_type = (int)$card['type'];
        $card_type_arg = (int)$card['type_arg'];
        $card_id = (int)$card['id'];
        
        // Get the full card information including who played it and who it targets
        $card_play_info = $this->getCardWithPlayInfo($card_id);
        $played_by = $card_play_info['played_by'] ? (int)$card_play_info['played_by'] : null;
        $target_player = $card_play_info['target_player'] ? (int)$card_play_info['target_player'] : null;
        
        // Trace card resolution info for debugging
        $card_name = $this->getCardName($card);
        $this->trace("Resolving card {$card_id} ({$card_name}): played by {$played_by}, targets {$target_player}");
        $this->trace("Card data: " . json_encode($card));
        $this->trace("Looking for CARD_EFFECTS[{$card_type}][{$card_type_arg}]");
        
        // Debug: Show all disaster cards in the database for this type
        if ($card_type === CardType::GlobalDisaster->value) {
            $all_global_cards = $this->getObjectListFromDb("SELECT card_id, card_type, card_type_arg, card_location FROM disaster_card WHERE card_type = " . CardType::GlobalDisaster->value);
            $this->trace("All Global Disaster cards in database: " . json_encode($all_global_cards));
        }
        
        $effects = $this->getCardEffects($card_type, $card_type_arg);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type $card_type, arg $card_type_arg");
        }
        
        // Check for the first non-zero attribute to determine the next state
        // Priority order: discard -> target_selection -> dice_roll -> amulet_decision -> recover_leader -> keep_card
        
        if ($effects['discard'] > 0) {
            $this->gamestate->nextState('discard');
            return;
        }
        
        // All local disaster cards require target selection first
        if ($card_type === CardType::LocalDisaster->value) {
            // Check if target is already selected
            if ($target_player === null) {
                // Set the active player to the one who played the card
                $this->gamestate->changeActivePlayer($played_by);
                $this->gamestate->nextState('selectTargets');
                return;
            }
        }
        
        if ($effects['convert_to_religion'] === "roll_d6" || $effects['convert_to_religion'] > 0) {
            // Convert to religion cards with specific targets need target selection first
            if ($target_player === null) {
                $this->gamestate->nextState('selectTargets');
                return;
            }
        }
        
        // Check for dice roll requirements first
        if ($effects['happiness_effect'] === "roll_d6" || 
            $effects['prayer_effect'] === "roll_d6" || 
            $effects['convert_to_religion'] === "roll_d6") {
            $this->gamestate->nextState('rollDice');
            return;
        }
        
        // After dice rolls (or if no dice needed), check if card has any negative effects that could be mitigated by amulets
        $hasNegativeEffects = ($effects['family_dies'] > 0) || 
                             ($effects['convert_to_atheist'] > 0) || 
                             (is_numeric($effects['happiness_effect']) && $effects['happiness_effect'] < 0);
        
        if ($hasNegativeEffects && !$amulets_resolved) {
            // Before transitioning to amulet resolution, check if anyone has amulets
            if ($this->anyPlayerHasAmulets($card, $played_by, $target_player)) {
                $this->gamestate->nextState('resolveAmulets');
                return;
            } else {
                // No one has amulets, skip amulet resolution
                $this->notifyAllPlayers('amuletPhaseSkipped', 
                    clienttranslate('No players have amulets to use'), []);
            }
        }
        
        if ($effects['recover_leader'] === true) {
            // Recover leader: set chief to 1 and notify UI
            $this->DbQuery("UPDATE player SET player_chief = 1 WHERE player_id = $played_by");
            
            $this->notifyAllPlayers('leaderRecovered', clienttranslate('${player_name} gained a new leader'), [
                'player_id' => $played_by,
                'player_name' => $this->getPlayerNameById($played_by)
            ]);
            
            // Apply basic card effects and move card to resolved
            $this->applyBasicCardEffects($card, $effects);
            $this->moveCardToResolved($card);
            
            // Check for more cards to resolve
            $this->gamestate->nextState('beginAllPlay');
            return;
        }
        
        if ($effects['keep_card'] === true || $effects['keep_card'] === 1) {
            // Increment temple or amulet counter based on card type
            if ($card_type === CardType::Bonus->value && $card_type_arg === BonusCard::Temple->value) {
                $this->DbQuery("UPDATE player SET player_temple = player_temple + 1 WHERE player_id = $played_by");
                $new_temple_count = (int)$this->getUniqueValueFromDb("SELECT player_temple FROM player WHERE player_id = $played_by");
                $this->notifyAllPlayers('templeIncremented', clienttranslate('${player_name} gained a temple'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by),
                    'temple_count' => $new_temple_count
                ]);
            } elseif ($card_type === CardType::Bonus->value && $card_type_arg === BonusCard::Amulets->value) {
                $this->DbQuery("UPDATE player SET player_amulet = player_amulet + 1 WHERE player_id = $played_by");
                $this->notifyAllPlayers('amuletIncremented', clienttranslate('${player_name} gained an amulet'), [
                    'player_id' => $played_by,
                    'player_name' => $this->getPlayerNameById($played_by)
                ]);
            }
            
            // Apply basic effects and move card to resolved
            $this->applyBasicCardEffects($card, $effects);
            $this->moveCardToResolved($card);
            
            // Check for more cards to resolve
            $this->gamestate->nextState('beginAllPlay');
            return;
        }
        
        // If no special effects, apply basic effects and continue resolving
        $this->applyBasicCardEffects($card, $effects);
        
        // Move card from resolving to resolved and continue
        $this->moveCardToResolved($card);
        
        // Check for more cards to resolve
        $this->gamestate->nextState('beginAllPlay');
    }

    /**
     * Get card effects from the constants array
     */
    private function getCardEffects(int $card_type, int $card_type_arg): ?array
    {
        // Define card effects directly to avoid global variable issues
        $CARD_EFFECTS = [
            CardType::GlobalDisaster->value => [
                GlobalDisasterCard::Tsunami->value => [
                    'prayer_cost' => 10,
                    'prayer_effect' => 0,
                    'happiness_effect' => -2,
                    'convert_to_atheist' => 1,
                    'family_dies' => 2,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
            ],
            CardType::LocalDisaster->value => [
                LocalDisasterCard::Tornado->value => [
                    'prayer_cost' => 4,
                    'prayer_effect' => 0,
                    'happiness_effect' => -1,
                    'convert_to_atheist' => 1,
                    'family_dies' => 1,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                LocalDisasterCard::Earthquake->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 0,
                    'happiness_effect' => -3,
                    'convert_to_atheist' => 0,
                    'family_dies' => 1,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                LocalDisasterCard::BadWeather->value => [
                    'prayer_cost' => 1,
                    'prayer_effect' => 0,
                    'happiness_effect' => -1,
                    'convert_to_atheist' => 0,
                    'family_dies' => 1,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                LocalDisasterCard::Locust->value => [
                    'prayer_cost' => 3,
                    'prayer_effect' => 0,
                    'happiness_effect' => -2,
                    'convert_to_atheist' => 0,
                    'family_dies' => 1,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                LocalDisasterCard::TempleDestroyed->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 0,
                    'happiness_effect' => -2,
                    'convert_to_atheist' => 0,
                    'family_dies' => 1,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
            ],
            CardType::Bonus->value => [
                BonusCard::GoodWeather->value => [
                    'prayer_cost' => 2,
                    'prayer_effect' => 0,
                    'happiness_effect' => 2,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::Fertility->value => [
                    'prayer_cost' => 6,
                    'prayer_effect' => 0,
                    'happiness_effect' => 1,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 1,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::Amulets->value => [
                    'prayer_cost' => 4,
                    'prayer_effect' => 0,
                    'happiness_effect' => 0,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::Festivities->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 0,
                    'happiness_effect' => 3,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::NewLeader->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 0,
                    'happiness_effect' => 0,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 0,
                    'recover_leader' => true,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::DoubleHarvest->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 5,
                    'happiness_effect' => 0,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 0,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 0,
                ],
                BonusCard::Temple->value => [
                    'prayer_cost' => 5,
                    'prayer_effect' => 0,
                    'happiness_effect' => 1,
                    'convert_to_atheist' => 0,
                    'family_dies' => 0,
                    'convert_to_religion' => 2,
                    'recover_leader' => false,
                    'discard' => 0,
                    'keep_card' => 1,
                ],
            ],
        ];
        
        // Add debugging
        $this->trace("Getting effects for type {$card_type}, arg {$card_type_arg}");
        
        if (!isset($CARD_EFFECTS[$card_type])) {
            $this->trace("Card type {$card_type} not found in CARD_EFFECTS. Available types: " . json_encode(array_keys($CARD_EFFECTS)));
            return null;
        }
        
        if (!isset($CARD_EFFECTS[$card_type][$card_type_arg])) {
            $this->trace("Card type_arg {$card_type_arg} not found for type {$card_type}. Available args: " . json_encode(array_keys($CARD_EFFECTS[$card_type])));
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
        $target_player = $card_play_info['target_player'] !== null ? (int)$card_play_info['target_player'] : null;
        
        // Handle global disasters with player choices
        if ($card_type === CardType::GlobalDisaster->value) {
            $this->applyGlobalDisasterEffects($card_id, $effects, $played_by);
        } else {
            // Handle local disasters and bonus cards
            $this->applyTargetedCardEffects($card_id, $effects, $played_by, $target_player);
        }
        
        // This will be implemented to apply the basic effects like prayer_effect, happiness_effect, convert_to_atheist
        // For now, just trace the effects with play information
        $this->trace("Applying basic effects for card {$card_id}: " . json_encode($effects) . 
                    " (played by: {$played_by}, targets: {$target_player})");
    }

    /**
     * Apply global disaster effects considering each player's choice
     */
    private function applyGlobalDisasterEffects(int $card_id, array $effects, ?int $played_by): void
    {
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
        
        // Get all players
        $sql = "SELECT player_id, player_prayer, player_faith, player_trade, player_culture 
                FROM player 
                WHERE player_eliminated = 0";
        $players = $this->getObjectListFromDb($sql);

        foreach ($players as $player) {
            $player_id = (int)$player['player_id'];
            
            // Calculate effect multiplier based on choice and player
            $multiplier = 1.0; // Default: normal effect
            
            if ($choice === 'avoid') {
                // Only the card player avoids the effect completely
                $multiplier = ($player_id === $card_player_id) ? 0.0 : 1.0;
            } elseif ($choice === 'double') {
                // Everyone (including the card player) gets double effect
                $multiplier = 2.0;
            }
            
            // Apply effects to this player with the calculated multiplier
            $this->applyEffectsToPlayer($player_id, $effects, $multiplier, $choice);
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
        
        // Apply multiplier to each effect
        if (isset($effects['faith_loss']) && $effects['faith_loss'] > 0) {
            $faith_loss = (int)($effects['faith_loss'] * $multiplier);
            if ($faith_loss > 0) {
                $effects_to_apply['faith_loss'] = $faith_loss;
            }
        }
        
        if (isset($effects['trade_loss']) && $effects['trade_loss'] > 0) {
            $trade_loss = (int)($effects['trade_loss'] * $multiplier);
            if ($trade_loss > 0) {
                $effects_to_apply['trade_loss'] = $trade_loss;
            }
        }
        
        if (isset($effects['culture_loss']) && $effects['culture_loss'] > 0) {
            $culture_loss = (int)($effects['culture_loss'] * $multiplier);
            if ($culture_loss > 0) {
                $effects_to_apply['culture_loss'] = $culture_loss;
            }
        }
        
        if (isset($effects['prayer_loss']) && $effects['prayer_loss'] > 0) {
            $prayer_loss = (int)($effects['prayer_loss'] * $multiplier);
            if ($prayer_loss > 0) {
                $effects_to_apply['prayer_loss'] = $prayer_loss;
            }
        }
        
        // For positive effects (bonus cards), also apply multiplier
        if (isset($effects['prayer_effect']) && $effects['prayer_effect'] != 0) {
            $prayer_effect = (int)($effects['prayer_effect'] * $multiplier);
            if ($prayer_effect != 0) {
                $effects_to_apply['prayer_effect'] = $prayer_effect;
            }
        }
        
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            $happiness_effect = (int)($effects['happiness_effect'] * $multiplier);
            if ($happiness_effect != 0) {
                $effects_to_apply['happiness_effect'] = $happiness_effect;
            }
        }
        
        // Apply the calculated effects using the existing applyCardEffects method
        if (!empty($effects_to_apply)) {
            $this->applyCardEffects($player_id, $effects_to_apply);
            
            // Determine if this is a bonus card (positive effects) or disaster card (negative effects)
            $has_positive_effects = isset($effects['prayer_effect']) || isset($effects['happiness_effect']) ||
                                   isset($effects['faith_effect']) || isset($effects['trade_effect']) || 
                                   isset($effects['culture_effect']);
            $has_negative_effects = isset($effects['faith_loss']) || isset($effects['trade_loss']) || 
                                   isset($effects['culture_loss']) || isset($effects['prayer_loss']);
            
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
        if (isset($effects['prayer_loss']) && $effects['prayer_loss'] > 0) {
            $updates[] = "player_prayer = GREATEST(0, player_prayer - " . (int)$effects['prayer_loss'] . ")";
        }
        
        // Note: Faith, trade, and culture effects are not stored in database for this game
        // They may be used for game logic but don't have persistent storage
        
        // Handle happiness effects
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            // Note: Happiness might need special handling based on game rules
            $updates[] = "player_happiness = GREATEST(0, player_happiness + " . (int)$effects['happiness_effect'] . ")";
        }
        if (isset($effects['happiness_effect']) && $effects['happiness_effect'] != 0) {
            // Note: Happiness might need special handling based on game rules
            $updates[] = "player_happiness = GREATEST(0, player_happiness + " . (int)$effects['happiness_effect'] . ")";
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
            // TODO: Implement family conversion logic
        }
        
        if (isset($effects['family_dies']) && $effects['family_dies'] > 0) {
            // TODO: Implement family death logic (with amulet protection)
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
        
        $card_name = $this->getCardName($resolving_card);
        $this->notifyAllPlayers("amuletDecision", 
            clienttranslate('Players with amulets must decide whether to use them against ${card_name}'), 
            [
                'card_name' => $card_name,
                'players_with_amulets' => $players_who_can_use_amulets
            ]
        );
    }

    private function applyCardEffectsWithoutAmulets(array $card): void
    {
        $effects = $this->getCardEffects($card['type'], $card['type_arg']);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$card['type']}, arg {$card['type_arg']}");
        }

        // Apply the card effects directly (dice results already incorporated if needed)
        if (!empty($this->diceResults)) {
            // Dice were rolled, apply effects with dice results
            $this->applyBasicCardEffectsWithDiceAndAmulets($card, $effects);
            $this->diceResults = []; // Clear dice results
        } else {
            // No dice were rolled, apply basic effects
            $this->applyBasicCardEffects($card, $effects);
        }
        
        // Move card from resolving to resolved and continue
        $this->moveCardToResolved($card);
        
        // Continue with next card resolution
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
            
            self::DbQuery("UPDATE player SET player_prayer = $prayers, player_happiness = $happiness WHERE player_id = $player_id");
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
     * Move all resolved cards to discard pile and clean up UI
     */
    private function cleanupResolvedCards(): void
    {
        // Get all resolved cards from both decks
        $resolved_disaster_cards = $this->disasterCards->getCardsInLocation('resolved');
        $resolved_bonus_cards = $this->bonusCards->getCardsInLocation('resolved');
        
        $all_resolved_cards = array_merge($resolved_disaster_cards, $resolved_bonus_cards);
        
        if (!empty($all_resolved_cards)) {
            // Move disaster cards to discard
            foreach ($resolved_disaster_cards as $card) {
                $this->disasterCards->moveCard($card['id'], 'discard');
            }
            
            // Move bonus cards to discard  
            foreach ($resolved_bonus_cards as $card) {
                $this->bonusCards->moveCard($card['id'], 'discard');
            }
            
            // Notify frontend to clean up resolved cards display
            $this->notifyAllPlayers('resolvedCardsCleanup', clienttranslate('All resolved cards have been discarded'), [
                'resolved_cards_count' => count($all_resolved_cards),
                'resolved_cards' => array_map(function($card) {
                    return [
                        'card_id' => $card['id'],
                        'card_type' => $card['type'],
                        'card_type_arg' => $card['type_arg']
                    ];
                }, $all_resolved_cards)
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

        $this->gamestate->nextState();
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

        $this->gamestate->nextState();
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

        $this->gamestate->nextState();
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
        
        $this->gamestate->nextState();
    }


    /***************************************/

    /***** Play card actions ******/
    public function actPlayCard(int $card_id): void
    {
        // 1. Check if action is allowed
        $this->checkAction('actPlayCard');

        // 2. Get current player (cast to int since moveCard expects int)
        $player_id = (int)$this->getActivePlayerId();

        // 3. Validate the card belongs to the player by checking both decks directly
        $card_in_hand = $this->getObjectFromDB("
            SELECT card_id, card_type, card_type_arg 
            FROM disaster_card 
            WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
            UNION
            SELECT card_id, card_type, card_type_arg 
            FROM bonus_card 
            WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id
        ");
        
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
        // Check if this card can be played according to Kalua rules (prayer cost)
        if (!$this->canPlayCard($player_id, $card)) {
            $card_name = $this->getCardName($card);
            throw new \BgaUserException("You don't have enough prayer points to play $card_name");
        }

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

        // 8. Send notification to all players (include card_type and card_type_arg for frontend)
        $notification_message = clienttranslate('${player_name} plays ${card_name}');
        $notification_args = [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'card_id' => $card_id,
            'card_name' => $this->getCardName($card),
            'card_type' => $card['type'],
            'card_type_arg' => $card['type_arg'],
            'played_by' => $player_id,
            'target_player' => $target_player
        ];
        
        // Add target information to notification if there's a specific target
        if ($target_player !== null && $target_player !== $player_id) {
            $notification_message = clienttranslate('${player_name} plays ${card_name} targeting ${target_name}');
            $notification_args['target_name'] = $this->getPlayerNameById($target_player);
        }
        
        $this->notifyAllPlayers('cardPlayed', $notification_message, $notification_args);

        // 8a. If round leader played a card, notify about button state change
        if ($player_id == $round_leader) {
            $this->notifyAllPlayers('roundLeaderPlayedCard', '', [
                'round_leader_played_card' => 1
            ]);
        }

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
            // Initialize choice for the player who played this global disaster
            $this->initializeGlobalDisasterChoice($card_id, $player_id);
            // Store the card ID for the choice actions
            $this->setGameStateValue('current_global_disaster', $card_id);
            $this->gamestate->nextState('phaseThreeCheckGlobal');
            return;
        } else {
            // Check if current player is round leader
            $current_player = $this->getActivePlayerId();
            $round_leader = $this->getGameStateValue("roundLeader");
            
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
    }

    public function actPlayCardPass(): void
    {
        // Clear the continuing play flag since the player is passing
        $this->setGameStateValue("round_leader_continuing_play", 0);
        
        $this->trace("KALUA passes their turn.");
        $this->gamestate->nextState('nextPlayerThree');
    }

    public function actSayConvert(): void
    {
        // Clear the continuing play flag since the round leader is moving to card resolution
        $this->setGameStateValue("round_leader_continuing_play", 0);
        
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
        
        // Log the restoration for debugging
        $this->trace("Returned from reflexive state to state $saved_state for player $saved_player");
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

        // Clear the stored card ID and transition to next state
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Check if current player is round leader
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        
        if ($current_player == $round_leader) {
            // Round leader can play again
            $this->gamestate->nextState("playAgain");
        } else {
            // Non-round leader moves to next player
            $this->gamestate->nextState("nextPlayerThree");
        }
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

        // Clear the stored card ID and transition to next state
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Check if current player is round leader
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        
        if ($current_player == $round_leader) {
            // Round leader can play again
            $this->gamestate->nextState("playAgain");
        } else {
            // Non-round leader moves to next player
            $this->gamestate->nextState("nextPlayerThree");
        }
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

        // Clear the stored card ID and transition to next state
        $this->setGameStateValue('current_global_disaster', 0);
        
        // Check if current player is round leader
        $current_player = $this->getActivePlayerId();
        $round_leader = $this->getGameStateValue("roundLeader");
        
        if ($current_player == $round_leader) {
            // Round leader can play again
            $this->gamestate->nextState("playAgain");
        } else {
            // Non-round leader moves to next player
            $this->gamestate->nextState("nextPlayerThree");
        }
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
        
        // Verify player has an amulet
        $amulet_count = (int)$this->getUniqueValueFromDb("SELECT player_amulet FROM player WHERE player_id = $player_id");
        if ($amulet_count <= 0) {
            throw new \BgaUserException("You don't have any amulets to use");
        }

        $player_name = $this->getPlayerNameById($player_id);
        
        if ($use_amulet) {
            // Player chooses to use their amulet
            $this->DbQuery("UPDATE player SET player_amulet = player_amulet - 1 WHERE player_id = $player_id");
            
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
        
        // BGA framework will automatically transition to 'beginAllPlay' when all multiactive players are done
    }

    private function applyCardEffectsWithAmuletChoices(): void
    {
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        $effects = $this->getCardEffects($resolving_card['type'], $resolving_card['type_arg']);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$resolving_card['type']}, arg {$resolving_card['type_arg']}");
        }

        // Apply the card effects considering amulet usage (dice results already incorporated if needed)
        if (!empty($this->diceResults)) {
            // Dice were rolled, apply effects with both dice results and amulet choices
            $this->applyBasicCardEffectsWithDiceAndAmulets($resolving_card, $effects);
            $this->diceResults = []; // Clear dice results
        } else {
            // No dice were rolled, apply basic effects with amulet protection
            $this->applyBasicCardEffectsWithAmulets($resolving_card, $effects);
        }
        
        // Clear the amulet usage tracking for next resolution
        $this->playerUsedAmulet = [];
        
        // Mark amulets as resolved for this card
        $this->amuletsResolved = true;
        
        // Continue with card resolution (effects like keep_card, recover_leader, etc.)
        $this->gamestate->nextState('beginAllPlay');
    }

    public function stRollDice(): void
    {
        // Get the currently resolving card to determine which players need to roll
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        $card_type = (int)$resolving_card['type'];
        $effects = $this->getCardEffects($resolving_card['type'], $resolving_card['type_arg']);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$resolving_card['type']}, arg {$resolving_card['type_arg']}");
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
        $played_by = $card_play_info['played_by'] ?? null;
        $target_player = $card_play_info['target_player'] ?? null;
        
        // Handle global disasters with player choices (amulets don't affect these as they have individual choices)
        if ($card_type === CardType::GlobalDisaster->value) {
            $this->applyGlobalDisasterEffects($card_id, $effects, $played_by);
        } else {
            // Handle local disasters and bonus cards with amulet consideration
            $this->applyTargetedCardEffectsWithAmulets($card_id, $effects, $played_by, $target_player);
        }
        
        $this->trace("Applied effects for card {$card_id} considering amulet usage: " . json_encode($this->playerUsedAmulet));
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
        
        // Check if all players have rolled their dice
        if ($this->gamestate->isPlayerActive($player_id) === false) {
            // All players have rolled, now apply card effects using the dice results
            $this->applyCardEffectsWithDiceResults();
        }
    }

    private function storeDiceResult(int $player_id, int $card_id, int $result): void
    {
        // Store dice result in a property for this resolution
        if (!isset($this->diceResults)) {
            $this->diceResults = [];
        }
        $this->diceResults[$player_id] = $result;
    }

    private function applyCardEffectsWithDiceResults(): void
    {
        $resolving_card = $this->getCardOnTop('resolving');
        if ($resolving_card === null) {
            throw new \BgaVisibleSystemException("No card currently resolving");
        }

        $effects = $this->getCardEffects($resolving_card['type'], $resolving_card['type_arg']);
        if ($effects === null) {
            throw new \BgaVisibleSystemException("Unknown card effect for type {$resolving_card['type']}, arg {$resolving_card['type_arg']}");
        }

        // After dice rolls, check if amulet decisions are needed for negative effects
        $hasNegativeEffects = ($effects['family_dies'] > 0) || 
                             ($effects['convert_to_atheist'] > 0) || 
                             (is_numeric($effects['happiness_effect']) && $effects['happiness_effect'] < 0);
        
        if ($hasNegativeEffects) {
            $this->gamestate->nextState('resolveAmulets');
            return;
        }

        // No amulet decisions needed, apply effects directly with dice results
        $this->applyBasicCardEffectsWithDiceAndAmulets($resolving_card, $effects);
        
        // Clear dice results for next resolution
        $this->diceResults = [];
        
        // Move card from resolving to resolved and continue
        $this->moveCardToResolved($resolving_card);
        
        // Continue with next card resolution
        $this->gamestate->nextState('beginAllPlay');
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
            $dice_result = $this->diceResults[$player_id] ?? 1; // Default to 1 if no result stored
            
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
            // Remove harmful effects (family_dies, convert_to_atheist)
            $effects['family_dies'] = 0;
            $effects['convert_to_atheist'] = 0;
            
            // Keep beneficial effects (positive happiness_effect, prayer_effect, convert_to_religion)
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
        
        // Check which players have cards in hand
        foreach ($all_players as $player_id => $player) {
            $card_count = $this->getCollectionFromDb("
                SELECT card_id FROM disaster_card WHERE card_location = 'hand' AND card_location_arg = $player_id
                UNION
                SELECT card_id FROM bonus_card WHERE card_location = 'hand' AND card_location_arg = $player_id
            ");
            
            if (count($card_count) > 0) {
                $players_with_cards[] = $player_id;
            }
        }
        
        if (empty($players_with_cards)) {
            // No players have cards to discard, move to next phase
            $this->gamestate->nextState('beginAllPlay');
            return;
        }
        
        // Set all players with cards as active for discard phase
        $this->gamestate->setPlayersMultiactive($players_with_cards, 'beginAllPlay');
    }
    /******************************/

    /***** helpers ******/

    /**
     * Set the play order for a card to enable FIFO resolution
     * @param int $card_id The card ID to set play order for
     * @param int $played_by The player ID who played this card
     * @param int|null $target_player The player ID this card targets (null for self/global effects)
     */
    private function setCardPlayOrder(int $card_id, int $played_by, ?int $target_player = null): void
    {
        // Get the next play order number by finding the max play_order + 1
        $max_disaster = (int)$this->getUniqueValueFromDb("SELECT COALESCE(MAX(play_order), 0) FROM disaster_card WHERE card_location = 'played'");
        $max_bonus = (int)$this->getUniqueValueFromDb("SELECT COALESCE(MAX(play_order), 0) FROM bonus_card WHERE card_location = 'played'");
        $next_order = max($max_disaster, $max_bonus) + 1;
        
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


    /******* Arg functions ************/
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
        $result["playedDisaster"] = $this->disasterCards->getCardsInLocation("played");
        $result["playedBonus"] = $this->bonusCards->getCardsInLocation("played");
        $result["resolvedDisaster"] = $this->disasterCards->getCardsInLocation("resolved");
        $result["resolvedBonus"] = $this->bonusCards->getCardsInLocation("resolved");

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
    private function drawCard_private(string $type, ?int $player_id = null) : void
    {
        $card = null;
        if ($player_id === null) {
            $player_id = (int)$this->getCurrentPlayerId();
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
}
