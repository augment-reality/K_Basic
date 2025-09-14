/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * kalua implementation : © August Delemeester haphazardeinsteinaugdog@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * kalua.js
 *
 * kalua user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone",

],

function (dojo, declare,) {
    return declare("bgagame.kalua", ebg.core.gamegui, {
        constructor: function(){
            console.log('kalua constructor');

            // Setup non-player based divs
            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="board_background">
                    <div id="hkboard"></div>
                    <div id="atheistFamilies"></div>
                    <div id="dice"></div>
                </div>

                <div id="card_areas">
                
                    <div id="playedCards">Played Cards:
                    </div>
                    <div id="resolvedCards">Resolved Cards:
                    </div>
                </div>


                <div id="player-tables" class="zone-container"></div>
            `);

            this.ID_GLOBAL_DISASTER = 1;
            this.ID_LOCAL_DISASTER = 2;
            this.ID_BONUS = 3;
            this.ID_AHTHIEST_STOCK = 6;
            this.prayerCounters = {};
            this.happinessCounters = {};
            this.cardCounters = {};
            this.templeCounters = {};
            this.amuletCounters = {};
            this.familyCounters = {};
        },
        
        setup: function(gamedatas) {
            console.log("Starting game setup");

            // Declare hexadecimal color maping for player tokens (default red/green/blue/orange/brown)
            

            
            // Create player areas
            Object.values(gamedatas.players).forEach(player => {
                document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                    <div id="player_area_${player.id}" class="player_area">
                        <div class="player_name">${player.name}</div>
                        <div id="${player.id}_cards" class="player_cards"></div>
                        <div id="${player.id}_families" class="player_families"></div>
                        <div id="${player.id}_InPlay" class="player_kept_cards">Kept Cards:</div>
                    </div>
                `);
            });

            // Set up players' side panels
            Object.values(gamedatas.players).forEach(player => {
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', `
                    <div>
                        <span>Prayer: <span id="panel_p_${player.id}"></span> <span id="icon_p" style="display:inline-block;vertical-align:middle;"></span></span><br>
                        <span>Happiness: <span id="panel_h_${player.id}"></span> <span id="icon_h" style="display:inline-block;vertical-align:middle;"></span></span><br>
                        <span>Leader: <span id="panel_l_${player.id}"></span> </span><br>
                        <span>Cards: <span id="panel_c_${player.id}"></span></span><br>
                        <span>Temples: <span id="panel_t_${player.id}"></span></span><br>
                        <span>Amulets: <span id="panel_a_${player.id}"></span></span><br>
                        <span>Families: <span id="panel_f_${player.id}"></span> <span id="icon_f" style="display:inline-block;vertical-align:middle;"></span></span>
                    </div>
                `);

                // Create prayer counter in player panel
                const counter_p = new ebg.counter();
                counter_p.create(document.getElementById(`panel_p_${player.id}`));
                counter_p.setValue(5); // All players start with 5 prayer
                this.prayerCounters[player.id] = counter_p;

                // Create happiness counter in player panel
                const counter_h = new ebg.counter();
                counter_h.create(document.getElementById(`panel_h_${player.id}`));
                counter_h.setValue(5); // All players start with 5 happiness
                this.happinessCounters[player.id] = counter_h;


                // Create card counter in player panel
                const counter_c = new ebg.counter();
                counter_c.create(document.getElementById(`panel_c_${player.id}`));
                counter_c.setValue(0);
                this.cardCounters[player.id] = counter_c;

                // Create temple counter in player panel
                const counter_t = new ebg.counter();
                counter_t.create(document.getElementById(`panel_t_${player.id}`));
                counter_t.setValue(0);
                this.templeCounters[player.id] = counter_t;

                // Create amulet counter in player panel
                const counter_a = new ebg.counter();
                counter_a.create(document.getElementById(`panel_a_${player.id}`));
                counter_a.setValue(0);
                this.amuletCounters[player.id] = counter_a;

                // Create family counter in player panel
                const counter_f = new ebg.counter();
                counter_f.create(document.getElementById(`panel_f_${player.id}`));
                counter_f.setValue(10);
                this.familyCounters[player.id] = counter_f;
            });


            // Initialize meeples as a stock in each player's family div
            Object.values(gamedatas.players).forEach(player => {
                this[`fams_${player.id}`] = new ebg.stock();
                this[`fams_${player.id}`].create(this, $(`${player.id}_families`), 30, 30);
                this[`fams_${player.id}`].image_items_per_row = 6;
                this[`fams_${player.id}`].setSelectionMode(0); // no selection

                // Make types for each color of meeple
                for (let i = 0; i < 7; i++) {
                    this[`fams_${player.id}`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i)
                    // addItemType(type: number, weight: number, image: string, image_position: number )
                }

                //Generate meeples based on family and chief count

                for (let i = 0; i < player.family; i++) {
                    this[`fams_${player.id}`].addToStock(this.ID_AHTHIEST_STOCK);
                }
                if (player.chief > 0) {
                    this[`fams_${player.id}`].addToStock(player.sprite); // 1 = chief meeple
                }    
                
            });


            //Add dice faces (each row is a different color)
            this['dice'] = new ebg.stock();
            this['dice'].create(this, document.getElementById('dice'), 50, 49.2);
            this['dice'].setSelectionMode(0);
            this['dice'].image_items_per_row = 6;
            for (let i = 1; i <= 30; i++) {
                this[`dice`].addItemType(i, i, g_gamethemeurl + 'img/d6_300_246.png', i);
            }

            // Object.values(gamedatas.players).forEach((player, idx) => {
            //     // Generate a random d6 value (1-6), then increment by 6 * player number (idx)
            //     const dieValue = Math.floor(Math.random() * 6) + (6 * idx);
            //     this['dice'].addToStock(dieValue);
            // });


            // Initialize and create atheist families stock
            this['atheists'] = new ebg.stock();
            this['atheists'].create(this, document.getElementById('atheistFamilies'), 30, 30);
            this['atheists'].setSelectionMode(0);
            this['atheists'].image_items_per_row = 6;
            for (let i = 0; i < 7; i++) {
                 this[`atheists`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i);
            }

            // Populate atheist families based on db value
            for (let i = 0; i < gamedatas.atheist_families; i++) {
                this['atheists'].addToStock(6); // 5 = atheist meeple
            }

            // Add ten children divs to hkboard with alternating widths of 33.3 and 30px
            const hkboard = document.getElementById('hkboard');
            for (let i = 0; i < 11; i++) {
                const childDiv = document.createElement('div');
                childDiv.id = `hkboard_child_${i}`;
                childDiv.style.width = `${i % 2 === 0 ? 33.3 : 30}px`;
                childDiv.style.height = '100%';
                childDiv.style.display = 'inline-block';
                hkboard.appendChild(childDiv);

                // Initialize and create hk token stock for each childDiv
                this[`hkToken_${i}`] = new ebg.stock();
                this[`hkToken_${i}`].create(this, childDiv, 30, 30);
                for (let j = 0; j < 5; j++) {
                    this[`hkToken_${i}`].addItemType(j, j, g_gamethemeurl + 'img/30_30_hktoken.png', j);
                }

                this[`hkToken_${i}`].setSelectionMode(0);
                // this[`hkToken_${i}`].image_items_per_row = 1;
                this[`hkToken_${i}`].container_div.width = "30px";
                this[`hkToken_${i}`].autowidth = false; // this is required so it obeys the width set above
                this[`hkToken_${i}`].use_vertical_overlap_as_offset = false; // this is to use normal vertical_overlap
                this[`hkToken_${i}`].vertical_overlap = 80; // overlap is 20%
                this[`hkToken_${i}`].horizontal_overlap = 0; // current bug in stock - needs "-1" to for Safari z-index to work
                this[`hkToken_${i}`].item_margin = 0; // has to be 0 if using overlap
                this[`hkToken_${i}`].updateDisplay(); // re-layout
            }

            // Get current HK token count for players - index for player color
            let hkTokenCount = 0;
            Object.values(gamedatas.players).forEach(player => {
                this[`hkToken_${5}`].addToStock(hkTokenCount); // type
                hkTokenCount++;

            });

            // // test moving tokens
            // this.movetokens(2, -2); // move token (type 3) one space to the right
            // this.movetokens(1, -5); // move token (type 3) one space to the right
            // this.movetokens(0, 3); // move token (type 3) one space to the right


            // Create stock for played cards
            this['playedCards'] = new ebg.stock();  
            this['playedCards'].create(this, document.getElementById('playedCards'), 120, 177.4);
            this['playedCards'].image_items_per_row = 5;
            this['playedCards'].setSelectionMode(0);

            // Initialize card stock for each player card div   
            Object.values(gamedatas.players).forEach(player => {
                this[`${player.id}_cards`] = new ebg.stock();
                this[`${player.id}_cards`].create(this, $(`${player.id}_cards`), 120, 177.4);
                this[`${player.id}_cards`].image_items_per_row = 5;
                this[`${player.id}_cards`].setSelectionMode(1); // single selection
                dojo.connect(this[`${player.id}_cards`], 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            });

            /* Add local disaster cards */
            const card_type_local_disaster = this.ID_LOCAL_DISASTER;
            const num_local_disaster_cards = 5;
            for (let card_id = 1; card_id <= num_local_disaster_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_local_disaster, card_id);
                console.log("uniqueID: " + uniqueId);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 0 - 4 for local disaster cards */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id - 1);
                });
            }

            /* Add global disaster cards */
            const card_type_global_disaster = this.ID_GLOBAL_DISASTER;
            const num_global_disaster_cards = 10;
            for (let card_id = 1; card_id <= num_global_disaster_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_global_disaster, card_id);
                console.log("uniqueID: " + uniqueId);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 5 - 14 for global disaster cards */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 4);
                });
            } 

            /* Add bonus cards */
            const card_type_bonus = this.ID_BONUS;
            const num_bonus_cards = 7;
            for (let card_id = 1; card_id <= num_bonus_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_bonus, card_id);
                console.log("uniqueID: " + uniqueId);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 15-21 for bonus cards */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 14);
                });
            } 

            /*** Update the UI with gamedata ***/

            /* Update counters */
            Object.values(gamedatas.players).forEach(player => {
                this.prayerCounters[player.id].setValue(player.prayer);
                this.happinessCounters[player.id].setValue(player.happiness);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
                /* TODO get each player's hand length to update counters */
                element = $(`panel_l_${player.id}`);
                if (player.chief == 1)
                {
                    element.innerHTML = `<span id="icon_cb_t" style="display:inline-block;vertical-align:middle;"></span>`;
                }
                else
                {
                    element.innerHTML = `<span id="icon_cb_f" style="display:inline-block;vertical-align:middle;"></span>`;
                }
            });

            /* Update player's hands with their drawn cards */
            Object.values(gamedatas.handDisaster).forEach(card => {
                console.log("id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })

            Object.values(gamedatas.handBonus).forEach(card => {
                console.log("id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })

            // Update sidebar counters based on gamedata
            Object.values(gamedatas.players).forEach(player => {
                this.prayerCounters[player.id].setValue(player.prayer);
                this.happinessCounters[player.id].setValue(player.happiness);
                this.cardCounters[player.id].setValue(player.cards);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
            });

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log("Ending game setup");
        },

       
        ///////////////////////////////////////////////////
        //// Game & client states

        onEnteringState: function(stateName, args) {
            console.log('Entering state: ' + stateName);
            switch (stateName) {
                default:
                    console.log("Entering unknown state: " + stateName);
                    // Perform actions for unknown state
                    break;
            }
        },

        onLeavingState: function(stateName) {
            console.log('Leaving state: ' + stateName);
                        switch (stateName) {
                case 'Initial_Draw':
                    if (this.isCurrentPlayerActive()) {
            if (this.gamedatas.hand) {
                for (var i in this.gamedatas.hand) {
                    var card = this.gamedatas.hand[i];
                    var color = card.type;
                    var value = card.type_arg;
                    this[`playerHand_${this.player_id}`].addToStockWithId(this.getCardUniqueId(color, value), card.id);
                }
            }
                    }
                    break;
                case 'Free_Action':
                    console.log("Leaving Free_Action state");
                    if (this.isCurrentPlayerActive()) {

                    }
                    break;

                default:
                    console.log("Entering unknown state: " + stateName);
                    // Perform actions for unknown state
                    break;
            }
            // Perform actions specific to leaving a state
        }, 

        onUpdateActionButtons: function(stateName, args) {
            console.log ('onUpdateActionButtons: ' + stateName);
            if (this.isCurrentPlayerActive()) 
            {
                switch (stateName)
                {
                    case 'initialDraw':
                        this.addActionButton('drawCardButton', _('Draw a Disaster card'), () => {
                            this.bgaPerformAction('actDrawCardInit', {
                                type: "disaster"
                            })
                        });

                        this.addActionButton('drawBonusCardButton', _('Draw a Bonus card'), () => {
                            this.bgaPerformAction('actDrawCardInit', {
                                type: "bonus"
                            })
                        });
                        break;
                    case 'phaseOneDraw':
                        this.addActionButton('drawCardButton', _('Draw a Disaster card'), () => {
                            this.bgaPerformAction('actDrawCard', {
                                type: "disaster"
                            })
                        });

                        this.addActionButton('drawBonusCardButton', _('Draw a Bonus card'), () => {
                            this.bgaPerformAction('actDrawCard', {
                                type: "bonus"
                            })
                        });
                        break;
                    case 'phaseTwoActivateLeader':
                        if (this.isCurrentPlayerActive()) 
                        {
                            this.addActionButton('giveSpeech-btn', _('Give a Speech'), () => {
                                this.bgaPerformAction("actGiveSpeech");
                            });
                            this.addActionButton('convertAtheist-btn', _('Convert Atheists'), () => {
                                this.bgaPerformAction("actConvertAtheists");
                            });
                            
                            /* check if there are enough atheists and disable the button if there aren't */
                            if (this['atheists'].count() == 0)
                            {
                                console.log('only ' + this['atheists'].count() + ' atheists left! disabling convert button');
                                dojo.addClass('convertAtheist-btn', 'disabled');
                            }

                            this.addActionButton('convertBeliever-btn', _('Convert Believer'), () => {
                                this.chooseConvertTarget()
                            });
                            this.addActionButton('sacrificeLeader-btn', _('Sacrifice Leader'), () => {
                                this.bgaPerformAction("actSacrificeLeader");
                            });
                        }
                        break;
                                        case 'phaseThreePlayCard':
                        if (this.isCurrentPlayerActive()) 
                        {
                            const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
                            const card = selectedCards[0];
                            this.onBtnPlayCard();
                        }
                        break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /* Maps card type (bonus, local disaster, global disaster) and type_id 
         * (which of those type cards it is) to a unique number */
        getCardUniqueId: function(type, type_id)
        {
            /* Unique ids will be based on the type and type_id */
            if (type == this.ID_GLOBAL_DISASTER) /* global disaster */
            {
                return type_id;
            }
            else if (type == this.ID_LOCAL_DISASTER) /* local disaster - 10 globals + this type_id */
            {
                return 10 + type_id;
            }
            else if (type == this.ID_BONUS) /* bonus = globals + local + type_id */
            {
                return 10 + 5 + type_id;
            }
            console.log("INVALID CARD TYPE!!"); /* TODO exception? */
            return 0;
        },

        playCardOnTable : function(player_id, color, value, card_id) {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                    this[`${player_id}_cards`].removeFromStockById(card_id);
                    this.cardCounters[player_id].incValue(-1);

            // Add card to played cards area
            const uniqueId = this.getCardUniqueId(parseInt(color), parseInt(value)); // Generate unique ID
            console.log("playing unique ID " + uniqueId)
            this['playedCards'].addToStockWithId(uniqueId, card_id); // Add card to played cards area  

            console.log(`Card ${card_id} played by player ${player_id}`);
        },

        drawCard: function(player, card_id, card_type, card_type_arg) {
            console.log("Drawing a card");

            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg)); // Generate unique ID
            console.log("drawing unique ID " + uniqueId)

            this[`${player}_cards`].addToStockWithId(uniqueId, card_id); // Add card to player's hand
            console.log(`Card ${card_id} added to player ${this.player}'s hand`);            
        },

        movetokens: function(tokenTypeToMove, desiredShift) {
            flag = false;
            for (let x = 0; x <= 10; x++) {
                    const tokens = this[`hkToken_${x}`].items;
                    Object.values(tokens).forEach(token => {
                        if (token.type == tokenTypeToMove && flag == false) {
                            // Remove from current stock
                            this[`hkToken_${x}`].removeFromStock(tokenTypeToMove);
                            // Add to adjacent stock
                            let newdiv = x + desiredShift;
                            if (newdiv < 0) newdiv = 0;
                            if (newdiv > 10) newdiv = 10;
                            this[`hkToken_${newdiv}`].addToStock(token.type);
                            flag = true; // only move one token
                        }
                    });
                }
        },

        giveSpeech: function(player_id) {
            console.log("Giving a speech");
            this.happinessCounters[player_id].incValue(1); // Increase happiness by 1
            this.movetokens(0, 1);
        },

        convertAtheists: function(player_id, num_atheists) {
            console.log("Converting " + num_atheists + " atheist families");
            const atheistFamilies = this['atheists'];
            const playerFamilies = this[`fams_${player_id}`];
            for (let i = 0; i < num_atheists; i++) {
                atheistFamilies.removeFromStock(this.ID_AHTHIEST_STOCK); // Remove from atheist families
                playerFamilies.addToStock(this.ID_AHTHIEST_STOCK); // Add to player's families
            }
            this.familyCounters[player_id].incValue(num_atheists);
        },

        chooseConvertTarget: function() {
            console.log("Choosing convert target");
            /* Present the option of the other players to the user as buttons */
            this.gamedatas.gamestate.descriptionmyturn = _('Choose a player to convert from: ');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            const otherPlayers = Object.values(this.gamedatas.players).filter(player => player.id != this.player_id);
            otherPlayers.forEach(player =>
            {
                /* TODO disable button if no families? but what if no one else has families? warning? */
                button = this.statusBar.addActionButton(_(player.name), () => 
                    this.bgaPerformAction('actConvertBelievers', { target_player_id: player.id}))
            });
        },

        convertBelievers : function(player_id, target_player_id) {
            const targetFamilies = this[`fams_${target_player_id}`];
            const playerFamilies = this[`fams_${player_id}`];
            targetFamilies.removeFromStock(this.ID_AHTHIEST_STOCK); // Remove from target player's families
            playerFamilies.addToStock(this.ID_AHTHIEST_STOCK); // Add to current player's families

            this.familyCounters[player_id].incValue(1);
            this.familyCounters[target_player_id].incValue(-1);
        },

        sacrificeLeader: function(player_id, player_no, num_atheists) {
            console.log("Sacrificing leader and gaining " + num_atheists + " atheists");
            const playerFamilies = this[`fams_${this.player_id}`];
            const atheistFamilies = this['atheists'];
            for (let i = 0; i < num_atheists; i++) 
            {
                atheistFamilies.removeFromStock(this.ID_AHTHIEST_STOCK); // Remove from atheist families
                playerFamilies.addToStock(this.ID_AHTHIEST_STOCK); // Add to player's families
            }
            playerFamilies.removeFromStock(player_no);
            this.familyCounters[player_id].incValue(num_atheists);
            element = $(`panel_l_${player_id}`);
            element.innerHTML = `<input type="checkbox" disabled>`;
        },

        onBtnPlayCard: function () {
        const action = "actPlayCard";
        if (!this.checkAction(action)) return;

        // Check the number of selected items
        const selectedCards = this.playerHand.getSelectedItems();
        if (selectedCards.length === 0) {
            this.showMessage(_('Please select a card'), 'error');
            return;
        }
        const card = selectedCards[0].id;
    },


        
        ///////////////////////////////////////////////////
        //// Player's action
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );

            // automatically listen to the notifications, based on the `notif_xxx` function on this class.
            this.bgaSetupPromiseNotifications();
        },

        onPlayerHandSelectionChanged: function () {
            const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
            if (selectedCards.length === 1 && this.checkAction('actPlayCard', true)) {
                const card_id = selectedCards[0].id;
                this[`${this.player_id}_cards`].unselectAll();
                this.bgaPerformAction('actPlayCard', { card_id: card_id });
            }
        },

        notif_playerDrewCard: async function( args )
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const type = args.card_type;
            const card_id = args.card_id;

            console.log( player_name + ' drew card ' + card_id + ' of type ' + type + ', type arg ' + args.card_type_arg);

            if (player_id == this.player_id)
            {
                this.drawCard(player_id, args.card_id, args.card_type, args.card_type_arg);
                console.log('It\'s me!');
            }
            /* Update counter */
            this.cardCounters[player_id].incValue(1);
            //this.gamedatas.players[player_id].addToStockWithId(); // card back visible to all players
        },

        notif_giveSpeech: async function( args )
        {
            const player_id = args.player_id;
            const player_name = args.player_name;

            console.log(player_name + ' gives a speech');
            this.giveSpeech(player_id);
        },

        notif_convertAtheists: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const num_atheists = args.num_atheists;

            console.log(player_name + ' converted ' + num_atheists + ' atheists');
            this.convertAtheists(player_id, num_atheists);
        },

        notif_sacrificeLeader: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const num_atheists = args.num_atheists;
            const player_no = args.player_no;

            console.log(player_name + '\'s leader gave a massive speech and sacrificed themselves - ' 
                            + num_atheists + ' were converted');

            this.sacrificeLeader(player_id, player_no, num_atheists);
        },

        notif_convertBelievers: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const target_id = args.target_id;
            const target_name = args.target_name;

            console.log(player_name + ' converted a believer from ' + target_name);
            this.convertBelievers(player_id, target_id);
        }

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

    });
});