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
    "ebg/zone"
],
function (dojo, declare) {
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
            this.prayerCounters = {};
            this.happinessCounters = {};
            this.cardCounters = {};
            this.templeCounters = {};
            this.amuletCounters = {};
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
                    <div> Prayer count: <span id="panel_p_${player.id}"></span> <br>
                     happiness:<span id="panel_h_${player.id}"></span><br>
                     Cards:<span id="panel_c_${player.id}"></span><br>
                     Temples:<span id="panel_t_${player.id}"></span><br>
                     Amulets:<span id="panel_a_${player.id}"></span>
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
            });


            // Initialize meeples as a stock in each player's family div
            Object.values(gamedatas.players).forEach(player => {
                this[`fams_${player.id}`] = new ebg.stock();
                this[`fams_${player.id}`].create(this, $(`${player.id}_families`), 30, 30);
                this[`fams_${player.id}`].image_items_per_row = 10;
                this[`fams_${player.id}`].setSelectionMode(0); // no selection

                // Make types for each color of meeple
                for (let i = 0; i < 10; i++) {
                    this[`fams_${player.id}`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i)
                    // addItemType(type: number, weight: number, image: string, image_position: number )
                }

                //Generate meeples based on family and chief count

                for (let i = 0; i < player.family; i++) {
                    this[`fams_${player.id}`].addToStock(8); // 9 = atheist meeple
                }
                if (player.chief > 0) {
                    this[`fams_${player.id}`].addToStock(1); // 1 = chief meeple
                }    
                
            });


            // //Add dice faces (each row is a different color)
            // this['dice'] = new ebg.stock();
            // this['dice'].create(this, document.getElementById('dice'), 50, 48.1);
            // this['dice'].setSelectionMode(0);
            // this['dice'].image_items_per_row = 6;
            // for (let i = 1; i <= 20; i++) {
            //     this[`dice`].addItemType(i, i, g_gamethemeurl + 'img/d6_300_481.png');
            // }

            //Add dice faces (each row is a different color)
            this['dice'] = new ebg.stock();
            this['dice'].create(this, document.getElementById('dice'), 50, 50);
            this['dice'].setSelectionMode(0);
            this['dice'].image_items_per_row = 6;
            for (let i = 1; i <= 6; i++) {
                this[`dice`].addItemType(i, i, g_gamethemeurl + 'img/d6_300_50.png');
            }


            // Initialize and create atheist families stock
            this['atheists'] = new ebg.stock();
            this['atheists'].create(this, document.getElementById('atheistFamilies'), 30, 30);
            this['atheists'].setSelectionMode(0);
            this['atheists'].image_items_per_row = 10;
            for (let i = 0; i < 10; i++) {
                 this[`atheists`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i);
            }

            // Populate atheist families based on db value
            for (let i = 0; i < gamedatas.atheist_families; i++) {
                this['atheists'].addToStock(8); // 8 = atheist meeple
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
                for (let j = 0; j < 10; j++) {
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
            Object.values(gamedatas.players).forEach(() => {
                this[`hkToken_${5}`].addToStock(hkTokenCount);
                hkTokenCount++;
            });

            
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
                this[`${player.id}_cards`].setSelectionMode(1);

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

            // DEBUG Add all cards to each player's hand
            // Object.values(gamedatas.players).forEach(player => {
            //     for (let i = 1; i <= 22; i++) {
            //         this[`${player.id}_cards`].addToStock(i);
            //     }
            // });


            // Add a die to each player's hand
            Object.values(gamedatas.players).forEach(player => {
                    // add a random die face
                    const rando = Math.floor(Math.random() * 6) + 1;
                    this[`dice`].addToStock(rando);
            });

            /*** Update the UI with gamedata ***/

            /* Update counters */
            Object.values(gamedatas.players).forEach(player => {
                this.prayerCounters[player.id].setValue(player.prayer);
                this.happinessCounters[player.id].setValue(player.happiness);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                /* TODO get each player's hand length to update counters */
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
                                giveSpeech();
                            });
                            this.addActionButton('convertAtheist-btn', _('Convert Atheist'), () => {
                                this.bgaPerformAction("actConvertAtheists");
                                convertAtheist();
                            });
                            
                            /* check if there are enough atheists and disable the button if there aren't */
                            if (this['atheists'].count() == 0)
                            {
                                console.log('only ' + this['atheists'].count() + ' atheists left! disabling convert button');
                                dojo.addClass('convertAtheist-btn', 'disabled');
                            }

                            this.addActionButton('convertBeliever-btn', _('Convert Believer'), () => {
                                this.bgaPerformAction("actConvertBelievers");
                                convertBeliever();
                            });
                            this.addActionButton('sacrificeLeader-btn', _('Sacrifice Leader'), () => {
                                this.bgaPerformAction("actSacrificeLeader");
                                sacrificeLeader();
                            });
                        }
                        break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        // getCardUniqueId: function(color, value) {
        //     return (color - 1) * 5 + (value - 1);
        // },

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

        drawCard: function(player, card_id, card_type, card_type_arg) {
            console.log("Drawing a card");

            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg)); // Generate unique ID
            console.log("drawing unique ID " + uniqueId)

            this[`${player}_cards`].addToStockWithId(uniqueId, card_id); // Add card to player's hand
            console.log(`Card ${card_id} added to player ${this.player}'s hand`);

            //Update counters for temples and amulets if needed
            // if (card_type == this.ID_BONUS) {
            //     if (card_type_arg == 1) { // Assuming type_arg 1 is temple
            //         const counter_t = this[`counter_t_${player}`];
            //         counter_t.incValue(1); // Increment temple count by 1
            //     } else if (card_type_arg == 2) { // Assuming type_arg 2 is amulet
            //         const counter_a = this[`counter_a_${player}`];
            //         counter_a.incValue(1); // Increment amulet count by 1
            //     }
            // }
            
        },


        giveSpeech: function(player_id) {
            console.log("Giving a speech");
            this.happinessCounters[player_id].incValue(1); // Increase happiness by 1
        },

        convertAtheist: function() {
            console.log("Converting atheist families");
            const atheistFamilies = this['atheists'];
            const playerFamilies = this[`fams_${this.player_id}`];
            const availableAtheists = atheistFamilies.getItemCount();
            const familiesToConvert = Math.min(availableAtheists, 2);
            for (let i = 0; i < familiesToConvert; i++) {
                atheistFamilies.removeFromStock(8); // Remove from atheist families
                playerFamilies.addToStock(8); // Add to player's families
            }
        },

        convertBeliever: function() {
            console.log("Converting believer families");
            const otherPlayers = Object.values(this.gamedatas.players).filter(player => player.id !== this.player_id);
            const targetPlayer = otherPlayers.find(player => this[`fams_${player.id}`].getItemCount() > 0);
            if (targetPlayer) {
                const targetFamilies = this[`fams_${targetPlayer.id}`];
                const playerFamilies = this[`fams_${this.player_id}`];
                targetFamilies.removeFromStock(8); // Remove from target player's families
                playerFamilies.addToStock(8); // Add to current player's families
            }
        },

        sacrificeLeader: function() {
            console.log("Sacrificing leader");
            const playerFamilies = this[`fams_${this.player_id}`];
            const atheistFamilies = this['atheists'];
            const familiesToAdd = Math.min(5, atheistFamilies.getItemCount());
            for (let i = 0; i < familiesToAdd; i++) {
                atheistFamilies.removeFromStock(8); // Remove from atheist families
                playerFamilies.addToStock(8); // Add to player's families
            }
        },


        
        ///////////////////////////////////////////////////
        //// Player's action
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );

            // automatically listen to the notifications, based on the `notif_xxx` function on this class.
            this.bgaSetupPromiseNotifications();
        },

        notif_playerDrewCard: async function( args )
        {
            const player_id = args.player_id;
            const type = args.card_type;
            const card_id = args.card_id;

            console.log( 'player ' + player_id + ' drew card ' + card_id + ' of type ' + type + ', type arg ' + args.card_type_arg);

            /* TODO add to hand of player who drew it*/
            if (player_id == this.player_id)
            {
                this.drawCard(player_id, args.card_id, args.card_type, args.card_type_arg);
                console.log('It\'s me!');
            }
            /* Update counter */
            this.cardCounters[player_id].incValue(1);
        },

        notif_giveSpeech: async function( args )
        {
            const player_id = args.player_id;

            console.log ('player ' + player_id + ' gives a speech');
            this.giveSpeech(player_id);
        }

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

    });
});