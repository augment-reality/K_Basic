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
                
                    <div id="playedCards"><div class="played_resolved">Played Cards:</div>
                    </div>
                    <div id="resolvedCards"><div class="played_resolved">Resolved Cards:</div>
                    </div>
                </div>


                <div id="player-tables" class="zone-container"></div>
            `);

            this.ID_GLOBAL_DISASTER = 1;
            this.ID_LOCAL_DISASTER = 2;
            this.ID_BONUS = 3;
            this.ID_AHTHIEST_STOCK = 5;
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
                        <span>Prayer: <span id="panel_p_${player.id}"></span> <span id="icon_p_${player.id}" class="icon_p" style="display:inline-block;vertical-align:middle;"></span></span><br>
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
                    this[`fams_${player.id}`].addToStock(player.sprite - 1); // 1 = chief meeple
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
                this['atheists'].addToStock(this.ID_AHTHIEST_STOCK); // 5 = atheist meeple
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
            // Place HK tokens for each player based on their sprite value from gamedatas
            Object.values(gamedatas.players).forEach(player => {
                // Add the correct token type for this player color
                this[`hkToken_${player.happiness}`].addToStock(player.sprite - 1);
            });

            // Create stock for played cards
            this['played'] = new ebg.stock();  
            this['played'].create(this, document.getElementById('playedCards'), 120, 177.4);
            this['played'].image_items_per_row = 5;
            this['played'].setSelectionMode(0);

            // Create stock for resolved cards
            this['resolved'] = new ebg.stock();
            this['resolved'].create(this, document.getElementById('resolvedCards'), 120, 177.4);
            this['resolved'].image_items_per_row = 5;
            this['resolved'].setSelectionMode(0);

            // Initialize card stock for each player card div   
            Object.values(gamedatas.players).forEach(player => {
                // Create cardbacks stock using the cardbacks div
                this[`${player.id}_cardbacks`] = new ebg.stock();
                this[`${player.id}_cardbacks`].create(this, $(`${player.id}_cards`), 120, 174);
                this[`${player.id}_cardbacks`].image_items_per_row = 2;
                this[`${player.id}_cardbacks`].setSelectionMode(0);

                // Create cards stock using the cards div
                this[`${player.id}_cards`] = new ebg.stock();
                this[`${player.id}_cards`].create(this, $(`${player.id}_cards`), 120, 177.4);
                this[`${player.id}_cards`].image_items_per_row = 5;
                this[`${player.id}_cards`].setSelectionMode(1); // single selection
                dojo.connect(this[`${player.id}_cards`], 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

                for (let card_id = 80; card_id <= 81; card_id++)
                {              
                    // Add card backs to stock
                    this[`${player.id}_cardbacks`].addItemType(card_id, card_id, g_gamethemeurl + 'img/Cards_Backs_240_174.png', card_id);
                }

            });



            /* Add local disaster cards */
            const card_type_local_disaster = this.ID_LOCAL_DISASTER;
            const num_local_disaster_cards = 5;
            for (let card_id = 1; card_id <= num_local_disaster_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_local_disaster, card_id);
                console.log("uniqueID: " + uniqueId);
                
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id - 1);
                
                // Add to resolved cards stock
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id - 1);
                
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
                
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 4);
                
                // Add to resolved cards stock
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 4);
                
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
                
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 14);
                
                // Add to resolved cards stock
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_All_600_887.png', card_id + 14);
                
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

            /* Populate played cards from database */
            Object.values(gamedatas.playedDisaster).forEach(card => {
                console.log("Adding played disaster card - id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                console.log("Calculated uniqueId: " + uniqueId);
                this['played'].addToStockWithId(uniqueId, card.id);
            });

            Object.values(gamedatas.playedBonus).forEach(card => {
                console.log("Adding played bonus card - id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                console.log("Calculated uniqueId: " + uniqueId);
                this['played'].addToStockWithId(uniqueId, card.id);
            });

            /* Populate resolved cards from database */
            Object.values(gamedatas.resolvedDisaster).forEach(card => {
                console.log("Adding resolved disaster card - id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                console.log("Calculated uniqueId: " + uniqueId);
                this['resolved'].addToStockWithId(uniqueId, card.id);
            });

            Object.values(gamedatas.resolvedBonus).forEach(card => {
                console.log("Adding resolved bonus card - id:" + card.id + ", type:" + card.type + ", arg:" + card.type_arg);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                console.log("Calculated uniqueId: " + uniqueId);
                this['resolved'].addToStockWithId(uniqueId, card.id);
            });

            /* Add cardbacks for other players' cards */
            Object.values(gamedatas.players).forEach(player => {
                if (player.id != this.player_id) {
                    const cardbackStock = this[`${player.id}_cardbacks`];
                    if (cardbackStock) {
                        let cardback_id_counter = 0;
                        
                        // Add disaster cardbacks based on actual disaster card count
                        const disasterCount = parseInt(player.disaster_cards) || 0;
                        for (let i = 0; i < disasterCount; i++) {
                            const unique_cardback_id = player.id * 1000 + cardback_id_counter++;
                            cardbackStock.addToStockWithId(80, unique_cardback_id); // 80 = disaster cardback
                        }
                        
                        // Add bonus cardbacks based on actual bonus card count
                        const bonusCount = parseInt(player.bonus_cards) || 0;
                        for (let i = 0; i < bonusCount; i++) {
                            const unique_cardback_id = player.id * 1000 + cardback_id_counter++;
                            cardbackStock.addToStockWithId(81, unique_cardback_id); // 81 = bonus cardback
                        }
                    }
                }
            });

            // Update sidebar counters based on gamedata
            Object.values(gamedatas.players).forEach(player => {
                this.prayerCounters[player.id].setValue(player.prayer);
                this.happinessCounters[player.id].setValue(player.happiness);
                this.cardCounters[player.id].setValue(player.cards);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
            });

            // Set initial round leader prayer icon to grayed version
            if (gamedatas.round_leader) {
                this.updateRoundLeaderIcons(null, gamedatas.round_leader);
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log("Ending game setup");
        },

       
        ///////////////////////////////////////////////////
        //// Game & client states

        onEnteringState: function(stateName, args) {
            console.log('Entering state: ' + stateName);
            switch (stateName) {
                case 'phaseOneDraw':
                    console.log("Entering phaseOneDraw state");
                    if (this.isCurrentPlayerActive()) {

                    }
                    break;

                case 'phaseTwoActivateLeader':
                    // Existing code for phaseTwoActivateLeader
                    break;

                case 'phaseThreeCheckGlobal':
                    if (this.isCurrentPlayerActive()) {
                        this.addActionButton('normal-btn', _('Normal Effect'), () => {
                            this.bgaPerformAction('actNormalGlobal', {});
                        });
                        this.addActionButton('avoid-btn', _('Avoid (Cost: Prayer)'), () => {
                            this.bgaPerformAction('actAvoidGlobal', {});
                        });
                        this.addActionButton('double-btn', _('Double (Cost: Prayer)'), () => {
                            this.bgaPerformAction('actDoubleGlobal', {});
                        });
                    }
                    break;

                case 'phaseThreeSelectTargets':
                    if (this.isCurrentPlayerActive()) {
                        this.setupTargetSelection();
                    }
                    break;

                case 'phaseThreeResolveAmulets':
                    if (this.isCurrentPlayerActive()) {
                        this.setupAmuletDecision();
                    }
                    break;

                case 'phaseThreeRollDice':
                    if (this.isCurrentPlayerActive()) {
                        this.setupDiceRoll();
                    }
                    break;

                case 'phaseThreeDiscard':
                    if (this.isCurrentPlayerActive()) {
                        this.setupDiscard();
                    }
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
                case 'phaseOneDraw':
                    this.addActionButton('drawCardButton', _('Draw a Disaster card'), () => {
                        this.bgaPerformAction('actDrawCard', {
                            type: "disaster"
                        })
                        }
                    )
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
                        this.addActionButton('playCard-btn', _('Play Card'), () => {
                            const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
                            if (selectedCards.length > 0) {
                                const card = selectedCards[0];
                                this.bgaPerformAction('actPlayCard', {
                                    card_id: card.id
                                });
                            } else {
                                this.showMessage(_('Please select a card first'), 'error');
                            }
                        });

                        this.addActionButton('buyCardReflex-btn', _('Buy Card (5 Prayer)'), () => {
                            this.bgaPerformAction('actGoToBuyCardReflex', {});
                        });

                        // Show pass button for all players (including round leader)
                        this.addActionButton('pass-btn', _('Pass'), () => {
                            // Check if button is disabled (has disabled CSS class)
                            const passBtn = document.getElementById('pass-btn');
                            if (passBtn && dojo.hasClass(passBtn, 'disabled')) {
                                this.showMessage(_('You must play at least one card before passing'), 'error');
                                return;
                            }
                            this.bgaPerformAction('actPlayCardPass', {});
                        });

                        // Disable pass button for round leader if they haven't played a card yet
                        if (this.player_id == this.gamedatas.round_leader && !this.gamedatas.round_leader_played_card) {
                            dojo.addClass('pass-btn', 'disabled');
                        }

                        // Only show convert button for the round leader
                        if (this.player_id == this.gamedatas.round_leader) {
                            this.addActionButton('convert-btn', _('CONVERT! (End Card Phase)'), () => {
                                this.bgaPerformAction('actSayConvert', {});
                            });
                            
                            // Disable convert button if round leader has already played a card
                            if (this.gamedatas.round_leader_played_card) {
                                dojo.addClass('convert-btn', 'disabled');
                            }
                        }

                        // Initially disable the play card button
                        const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
                        if (selectedCards.length === 0) {
                            dojo.addClass('playCard-btn', 'disabled');
                        }
                        break;
                    case 'reflexiveBuyCard':
                        this.addActionButton('buyDisaster-btn', _('Buy Disaster Card (5 Prayer)'), () => {
                            this.bgaPerformAction('actDrawCardAnytime', {
                                type: 'disaster'
                            });
                        });

                        this.addActionButton('buyBonus-btn', _('Buy Bonus Card (5 Prayer)'), () => {
                            this.bgaPerformAction('actDrawCardAnytime', {
                                type: 'bonus'
                            });
                        });

                        this.addActionButton('cancel-btn', _('Cancel'), () => {
                            this.bgaPerformAction('actCancelBuyCard', {});
                        });
                        break;
                    case 'phaseThreeDiscard':
                        this.addActionButton('discard-btn', _('Discard Card'), () => {
                            const selectedCardsForDiscard = this[`${this.player_id}_cards`].getSelectedItems();
                            if (selectedCardsForDiscard.length > 0) {
                                const card = selectedCardsForDiscard[0];
                                this.bgaPerformAction('actDiscard', {
                                    card_id: card.id
                                });
                            } else {
                                this.showMessage(_('Please select a card to discard first'), 'error');
                            }
                        });

                        // Initially disable the discard button until a card is selected
                        const selectedCardsForDiscard = this[`${this.player_id}_cards`].getSelectedItems();
                        if (selectedCardsForDiscard.length === 0) {
                            dojo.addClass('discard-btn', 'disabled');
                        }
                        break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /* Maps card type (bonus, local disaster, global disaster) and type_id 
         * (which of those type cards it is) to a unique number*/
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
            this['played'].addToStockWithId(uniqueId, card_id); // Add card to played cards area  

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
            // Use the player's sprite value from gamedatas to move the correct token
            const sprite = this.gamedatas.players[player_id].sprite;
            this.movetokens(sprite-1, 1);
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

        setupTargetSelection: function() {
            console.log("Setting up target selection for local disaster");
            /* Present other players as target options */
            this.gamedatas.gamestate.descriptionmyturn = _('Choose a player to target with your disaster: ');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            
            // Get all players except the current player
            const otherPlayers = Object.values(this.gamedatas.players).filter(player => player.id != this.player_id);
            
            otherPlayers.forEach(player => {
                this.addActionButton(`target-player-${player.id}`, _(player.name), () => {
                    this.bgaPerformAction('actSelectPlayer', { player_id: player.id });
                });
            });
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

        setupAmuletDecision: function() {
            console.log("Setting up amulet decision for player");
            /* Present amulet usage choice to the player */
            this.gamedatas.gamestate.descriptionmyturn = _('Do you want to use an amulet to avoid the disaster effects?');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            
            this.addActionButton('use-amulet-btn', _('Use Amulet'), () => {
                this.bgaPerformAction('actAmuletChoose', { use_amulet: true });
            });
            
            this.addActionButton('no-amulet-btn', _('Do Not Use Amulet'), () => {
                this.bgaPerformAction('actAmuletChoose', { use_amulet: false });
            });
        },

        setupDiceRoll: function() {
            console.log("Setting up dice roll for player");
            /* Present dice rolling options to the player */
            this.gamedatas.gamestate.descriptionmyturn = _('Roll a die to determine the card effect:');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            
            // Create buttons for each possible dice result (1-6)
            for (let i = 1; i <= 6; i++) {
                this.addActionButton(`roll-${i}-btn`, _(`Roll ${i}`), () => {
                    this.bgaPerformAction('actRollDie', { result: i });
                });
            }
        },

        setupDiscard: function() {
            console.log("Setting up discard phase for player");
            /* Present discard options to the player */
            this.gamedatas.gamestate.descriptionmyturn = _('Choose a card to discard due to riots:');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            
            this.addActionButton('discard-btn', _('Discard Selected Card'), () => {
                const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
                if (selectedCards.length > 0) {
                    const card = selectedCards[0];
                    this.bgaPerformAction('actDiscard', {
                        card_id: card.id
                    });
                } else {
                    this.showMessage(_('Please select a card to discard first'), 'error');
                }
            });
            
            // Initially disable the discard button until a card is selected
            const selectedCards = this[`${this.player_id}_cards`].getSelectedItems();
            if (selectedCards.length === 0) {
                dojo.addClass('discard-btn', 'disabled');
            }
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
            playerFamilies.removeFromStock(player_no-1); // Remove chief meeple
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
            
            // Enable/disable the Play Card button based on selection
            if (selectedCards.length > 0) {
                if ($('playCard-btn')) {
                    dojo.removeClass('playCard-btn', 'disabled');
                }
                if ($('discard-btn')) {
                    dojo.removeClass('discard-btn', 'disabled');
                }
            } else {
                if ($('playCard-btn')) {
                    dojo.addClass('playCard-btn', 'disabled');
                }
                if ($('discard-btn')) {
                    dojo.addClass('discard-btn', 'disabled');
                }
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
            else
            {
                // Show cardback to other players
                const cardbackStock = this[`${player_id}_cardbacks`];
                if (cardbackStock) {
                    // Determine cardback type based on card type
                    let cardback_id;
                    if (type == 1 || type == 2) { // GlobalDisaster or LocalDisaster
                        cardback_id = 80; // disaster cardback
                    } else { // Bonus or any other type
                        cardback_id = 81; // bonus cardback (fallback for unknown types)
                    }
                    
                    cardbackStock.addToStockWithId(cardback_id, card_id);
                }
            }
            
            /* Update counter */
            this.cardCounters[player_id].incValue(1);
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
        },

        notif_cardPlayed: function(args) {
            // Remove the card from the correct player's hand if the stock exists
            const playerCardsStock = this[`${args.player_id}_cards`];
            if (playerCardsStock) {
                playerCardsStock.removeFromStockById(args.card_id);
            }
            
            // Remove the cardback from other players' view
            const cardbackStock = this[`${args.player_id}_cardbacks`];
            if (cardbackStock) {
                cardbackStock.removeFromStockById(args.card_id);
            }
            
            if (this.cardCounters[args.player_id]) {
                this.cardCounters[args.player_id].incValue(-1);
            }
            // Add the card to the played stock
            const uniqueId = this.getCardUniqueId(parseInt(args.card_type), parseInt(args.card_type_arg));
            if (this['played']) {
                this['played'].addToStock(uniqueId);
            }
            console.log(`Card ${args.card_id} played by player ${args.player_id}`);
        },

        notif_cardBought: function(args) {
            // Update card counter for the player who bought a card
            if (this.cardCounters[args.player_id]) {
                this.cardCounters[args.player_id].incValue(1);
            }
            console.log(`Player ${args.player_id} bought a card`);

        },

        notif_cardDrawn: function(args) {
            // Private notification - add the card to the player's hand if it's the current player
            if (args.player_id == this.player_id && args.card) {
                const card = args.card;
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                const playerCardsStock = this[`${this.player_id}_cards`];
                if (playerCardsStock) {
                    playerCardsStock.addToStockWithId(uniqueId, card.id);
                }
            }
        },

        notif_prayerSpent: function(args) {
            // Update prayer counter for the player who spent prayer points
            if (this.prayerCounters[args.player_id]) {
                this.prayerCounters[args.player_id].setValue(args.new_prayer_total);
            }
            console.log(`Player ${args.player_id} spent ${args.prayer_spent} prayer points`);
        },

        notif_roundLeaderChanged: function(args) {
            // Update prayer icons when round leader changes
            this.updateRoundLeaderIcons(args.old_leader, args.player_id);
            console.log(`Round leader changed from ${args.old_leader} to ${args.player_id}`);
        },

        notif_roundLeaderPlayedCard: function(args) {
            // Enable the pass button for round leader after they play a card
            this.gamedatas.round_leader_played_card = args.round_leader_played_card;
            
            // Enable pass button if it exists and is currently disabled
            const passBtn = document.getElementById('pass-btn');
            if (passBtn && dojo.hasClass(passBtn, 'disabled')) {
                dojo.removeClass(passBtn, 'disabled');
            }
            
            // Disable convert button if it exists after round leader plays a card
            const convertBtn = document.getElementById('convert-btn');
            if (convertBtn && !dojo.hasClass(convertBtn, 'disabled')) {
                dojo.addClass(convertBtn, 'disabled');
            }
            
            console.log('Round leader has now played a card, pass button enabled, convert button disabled');
        },

        notif_roundLeaderTurnStart: function(args) {
            // Reset round leader state when their turn starts
            this.gamedatas.round_leader_played_card = args.round_leader_played_card;
            
            // Re-enable convert button if round leader hasn't played a card yet
            const convertBtn = document.getElementById('convert-btn');
            if (convertBtn && !this.gamedatas.round_leader_played_card && dojo.hasClass(convertBtn, 'disabled')) {
                dojo.removeClass(convertBtn, 'disabled');
            }
            
            console.log('Round leader turn started, pass button will be disabled until they play a card');
        },

        updateRoundLeaderIcons: function(old_leader, new_leader) {
            // Reset old leader's prayer icon to normal
            if (old_leader) {
                const oldIcon = document.getElementById(`icon_p_${old_leader}`);
                if (oldIcon) {
                    oldIcon.className = 'icon_p';
                }
            }
            
            // Set new leader's prayer icon to grayed version
            if (new_leader) {
                const newIcon = document.getElementById(`icon_p_${new_leader}`);
                if (newIcon) {
                    newIcon.className = 'icon_pg';
                }
            }
        },

        notif_targetSelected: function(args) {
            console.log('Target selected for disaster card:', args);
            // The target selection is complete, the game will continue with card resolution
            // No specific UI updates needed here as the game will transition to the next state
        },

        notif_amuletDecision: function(args) {
            console.log('Amulet decision phase started:', args);
            // Players with amulets can now decide whether to use them
        },

        notif_amuletUsed: function(args) {
            console.log('Player used amulet:', args);
            const player_name = args.player_name;
            // Visual feedback could be added here to show amulet usage
        },

        notif_amuletNotUsed: function(args) {
            console.log('Player chose not to use amulet:', args);
            const player_name = args.player_name;
            // Visual feedback could be added here
        },

        notif_diceRollRequired: function(args) {
            console.log('Dice roll required:', args);
            // Players who need to roll dice will be prompted in the setupDiceRoll method
        },

        notif_diceRolled: function(args) {
            console.log('Player rolled dice:', args);
            const player_name = args.player_name;
            const result = args.result;
            // Visual feedback could be added here to show the dice result
        },

        notif_templeIncremented: function(args) {
            console.log('Temple incremented for player:', args);
            const player_id = args.player_id;
            // Update temple counter
            if (this.templeCounters[player_id]) {
                this.templeCounters[player_id].incValue(1);
            }
        },

        notif_amuletIncremented: function(args) {
            console.log('Amulet incremented for player:', args);
            const player_id = args.player_id;
            // Update amulet counter
            if (this.amuletCounters[player_id]) {
                this.amuletCounters[player_id].incValue(1);
            }
        },

        notif_templeBonus: function(args) {
            console.log('Temple bonus notification:', args);
            // Just a notification message - the prayer counter will be updated by playerCountsChanged
        },

        notif_playerCountsChanged: function(args) {
            console.log('Player counts changed:', args);
            const player_id = args.player_id;
            
            // Update all player counters
            if (this.familyCounters[player_id]) {
                this.familyCounters[player_id].setValue(args.family_count);
            }
            if (this.prayerCounters[player_id]) {
                this.prayerCounters[player_id].setValue(args.prayer);
            }
            if (this.happinessCounters[player_id] && args.happiness !== undefined) {
                this.happinessCounters[player_id].setValue(args.happiness);
            }
            if (this.templeCounters[player_id] && args.temple_count !== undefined) {
                this.templeCounters[player_id].setValue(args.temple_count);
            }
        },

        notif_leaderRecovered: function(args) {
            console.log('Leader recovered for player:', args);
            const player_id = args.player_id;
            
            // Add chief meeple back to player's family stock
            const playerFamilies = this[`fams_${player_id}`];
            if (playerFamilies && this.gamedatas.players[player_id]) {
                const sprite = this.gamedatas.players[player_id].sprite;
                playerFamilies.addToStock(sprite - 1); // sprite - 1 = chief meeple type
            }
            
            // Update leader display in player panel
            const leaderElement = document.getElementById(`panel_l_${player_id}`);
            if (leaderElement) {
                leaderElement.innerHTML = `<span id="icon_cb_t" style="display:inline-block;vertical-align:middle;"></span>`;
            }
        },

        notif_cardDiscarded: function(args) {
            console.log('Card discarded:', args);
            const player_id = args.player_id;
            const card_id = args.card_id;
            
            // Remove the card from the player's hand if the stock exists
            const playerCardsStock = this[`${player_id}_cards`];
            if (playerCardsStock) {
                playerCardsStock.removeFromStockById(card_id);
            }
            
            // Remove the cardback from other players' view
            const cardbackStock = this[`${player_id}_cardbacks`];
            if (cardbackStock) {
                cardbackStock.removeFromStockById(card_id);
            }
            
            // Update card counter
            if (this.cardCounters[player_id]) {
                this.cardCounters[player_id].incValue(-1);
            }
            
            console.log(`Card ${card_id} discarded by player ${player_id}`);
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

    });
});