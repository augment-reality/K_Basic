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
                </div>
                <div id="playedCards">Played Cards:
                </div>

                <div id="player-tables" class="zone-container"></div>
            `);
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
                        <div id="${player.id}_dice" class="player_dice">is your die</div>
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
            //counter_p.setValue(gamedatas[players].prayer);
                    
            // Create happiness counter in player panel
            const counter_h = new ebg.counter();
            counter_h.create(document.getElementById(`panel_h_${player.id}`));
            //counter_h.setValue(gamedatas.players[player.id].happiness);

            // Create card counter in player panel
            const counter_c = new ebg.counter();
            this[`counter_c_${player.id}`] = counter_c;
            counter_c.create(document.getElementById(`panel_c_${player.id}`));
            counter_c.setValue(0);


            // Initialize meeples as a stock in each player's family div
            Object.values(gamedatas.players).forEach(player => {
                this[`fams_${player.id}`] = new ebg.stock();
                this[`fams_${player.id}`].create(this, $(`${player.id}_families`), 30, 30);
                this[`fams_${player.id}`].image_items_per_row = 10;

                // Initialize dice as a stock in each player's dice div
                this[`${player.id}_dice`] = new ebg.stock();
                this[`${player.id}_dice`].create(this, $(`${player.id}_dice`), 50, 50);
                this[`${player.id}_dice`].image_items_per_row = 6;
                
                //Add types for dice faces
                for (let i = 1; i <= 6; i++) {
                    this[`${player.id}_dice`].addItemType(i, i, g_gamethemeurl + 'img/d6_300_50.png');
                }

                // Make types for each color of meeple
                for (let i = 0; i < 10; i++) {
                    this[`fams_${player.id}`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png')
                    // addItemType(type: number, weight: number, image: string, image_position: number )
                }

                // Use PHP-provided values for family/chief
                // Object.values(gamedatas.players).forEach(player => {
                // for (let i = 0; i < this.gamedatas.player_id[player.family]; i++) {
                //     this[`${player.id}_families`].addToStock(8); // 8 = atheist meeple
                // }
                // if (player.chief > 0) {
                //     this[`fams_${player.id}`].addToStock(1); // 1 = chief meeple
                // }
                // })
    
                
            });

            // Initialize and create atheist families stock
            this['atheists'] = new ebg.stock();
            this['atheists'].create(this, document.getElementById('atheistFamilies'), 30, 30);
            this['atheists'].setSelectionMode(0);
            this['atheists'].image_items_per_row = 10;
            for (let i = 0; i < 10; i++) {
                 this[`atheists`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i);
            }
            
            // Use PHP-provided value for atheist families
            for (let i = 0; i < this.gamedatas.atheist_families; i++) {
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
            Object.values(gamedatas.players).forEach(player => {
                this[`hkToken_${5}`].addToStock(3);
                hkTokenCount++;
            });


            // Create stock for played cards
            this['playedCards'] = new ebg.stock();  
            this['playedCards'].create(this, document.getElementById('playedCards'), 120, 174);
            this['playedCards'].image_items_per_row = 5;
            this['playedCards'].setSelectionMode(0);

            // Initialize card stock for each player card div
            Object.values(gamedatas.players).forEach(player => {
                this[`${player.id}_cards`] = new ebg.stock();
                this[`${player.id}_cards`].create(this, $(`${player.id}_cards`), 120, 174);
                this[`${player.id}_cards`].image_items_per_row = 5;
                this[`${player.id}_cards`].setSelectionMode(1);

            });

            // Create disaster card types
            for (let color = 1; color <= 3; color++) {
                for (let value = 1; value <= 5; value++) {
                    const card_type_id = this.getCardUniqueId(color, value);
                    Object.values(gamedatas.players).forEach(player => {
                        this[`${player.id}_cards`].addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/Cards_Disaster_600_522.png', card_type_id);
                    });
                }
            }


            });

            // Add three cards to each player's hand
            Object.values(gamedatas.players).forEach(player => {
                for (let i = 1; i <= 3; i++) {
                    // const card_type_id = this.getCardUniqueId(Math.floor(Math.random() * 3) + 1, Math.floor(Math.random() * 5) + 1); // Example card type id
                    const rando = Math.floor(Math.random() * 14) + 1;
                    this[`${player.id}_cards`].addToStock(rando);
                }
            });

            // Add a die to each player's hand
            Object.values(gamedatas.players).forEach(player => {
                    // add a random die face
                    const rando = Math.floor(Math.random() * 5) + 1;
                    this[`${player.id}_dice`].addToStock(rando);
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
                case 'Free_Action':
                    console.log("Entering Free_Action state");
                    if (this.isCurrentPlayerActive()) {
                        this.addActionButton('giveSpeech-btn', _('Give a Speech'), () => {
                            this.giveSpeech();
                        });
                        this.addActionButton('convertAtheist-btn', _('Convert Atheist'), () => {
                            this.convertAtheist();
                        });
                        this.addActionButton('convertBeliever-btn', _('Convert Believer'), () => {
                            this.convertBeliever();
                        });
                        this.addActionButton('sacrificeLeader-btn', _('Sacrifice Leader'), () => {
                            this.sacrificeLeader();
                        });
                        this.addActionButton('actPass-btn', _('Pass'), () => this.bgaPerformAction("actPass"), null, null, 'gray');
                    }
                    break;
                case 'waitingForPlayers':
                    console.log("Entering waitingForPlayers state");
                    // Perform actions specific to waitingForPlayers state
                    break;
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
                        this.addActionButton('drawDisasterCardButton', _('Draw a Disaster card'), () => {
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
                        this.addActionButton('drawDisasterCardButton', _('Draw a Disaster card'), () => {
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
                            this.addActionButton('convertAtheist-btn', _('Convert Atheist'), () => {
                                this.bgaPerformAction("actGiveSpeech");
                            });
                            this.addActionButton('convertBeliever-btn', _('Convert Believer'), () => {
                                /* This one requires more decisions */
                                this.convertBeliever();
                            });
                            this.addActionButton('sacrificeLeader-btn', _('Sacrifice Leader'), () => {
                                this.bgaPerformAction("actMassiveSpeech");
                            });
                        }
                        break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        getCardUniqueId: function(color, value) {
            return (color - 1) * 5 + (value - 1);
        },

            drawDisasterCard: function(player, card) {
            console.log("Drawing a disaster card");

            if (player && card) {
                const color = Math.floor(card.type / 5) + 1; // Extract color from card type
                const value = (card.type % 5) + 1;          // Extract value from card type
                const uniqueId = this.getCardUniqueId(color, value); // Generate unique ID

                this['playedCards'].removeFromStockById(card.id); // Remove card from the deck
                this[`${player}_cards`].addToStockWithId(uniqueId, card.id); // Add card to player's hand
                console.log(`Card ${card} added to player ${this.player}'s hand`);
            } else {
                console.log("No cards left in the deck");
            }
        },


        giveSpeech: function() {
            console.log("Giving a speech");
            const happinessCounter = document.getElementById(`panel_h_${this.player_id}`);
            const counter = new ebg.counter();
            counter.create(happinessCounter);
            counter.incValue(1); // Increase happiness by 1
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
            // Set leader to 0 (remove leader from play)
            this.gamedatas.players[this.player_id].chief = 0;
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
            // const player_id = args.player_id;
            // const type = args.card_type;
            // const card_id = args.card_id;

            // //console.log( 'player ' + player_id + ' drew card ' + card_id + ' of type ' + type);

            // /* TODO add to hand of player who drew it*/
            // if (player_id == this.player_id)
            // {
            //     //console.log('It\'s me!');
            // }
        },

        notif_giveSpeech: async function( args )
        {
            const player_id = args.player_id;

            console.log ('player ' + player_id + ' gives a speech');
            if (player_id == this.player_id)
            {
                giveSpeech();
            }
        }

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

    });
});