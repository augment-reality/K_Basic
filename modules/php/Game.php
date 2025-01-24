<?php

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
            "Update_Count" => 10,
            "Active_Draw" => 11,
            "Free_Action" => 20,
            "Convert" => 40,
            "Gain_Prayer" => 50,
            "Check_Winner" => 61,
            "Check_Tie" => 62,
            "Active_Player_Increment" => 70,
            "End_Game" => 89
        ]);        
     
    }

    /*      //Make two decks: bonus and disaster
        $this->disasterCards = $this->getNew( "module.common.deck" );
        $this->disasterCards ->init( "disaster_card" );
        $this->bonusCards = $this->getNew( "module.common.deck" );
        $this->bonusCards ->init( "bonus_card" );
    } */

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

    public function argPlayerTurn(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "playableCardsIds" => [1, 2],
        ];
    }

    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Game state action, example content.
     *
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

    protected function getGameName()
    {
        return "kalua";
    }

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

        // // Initial family setup?
        // switch ($this->getGameStateValue('player_count')) {
        //     default:
        //         $hk_families_start = 9;
        //         break;
        //     case 1:
        //         $hk_families_start = 12;
        //         break;
        //     case 2:
        //         $hk_families_start = 15;
        //         break;
        // }

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
    }