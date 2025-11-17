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
    "dojo","dojo/_base/declare","dijit/Tooltip",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone",
],
function (dojo, declare,) {
    return declare("bgagame.kalua", ebg.core.gamegui, {
        constructor: function(){
            // Setup non-player based divs
            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="board_background">
                    <div id="hkboard"></div>
                    <div id="atheistFamilies"></div>
                    <div id="dice"></div>
                </div>
                <div id="card_areas">
                    <div id="playedCards">
                        <div class="kalua_played_resolved">PLAYED CARDS:</div>
                        <div id="playedCardsContent"></div>
                    </div>
                    <div id="resolvedCards" style="position: relative;">
                        <div class="kalua_played_resolved">RESOLVED CARDS:</div>
                        <div id="resolvedCardsContent"></div>
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
            // Auto-roll tracking to prevent multiple attempts
            this.autoRollAttempted = false;
            // HK Token movement timing variables
            this.hkTokenMoveDelay = 1200;      // Delay before starting token movement
            this.hkTokenTransferDelay = 200;  // Delay between remove and add operations
            // Play order tracking for proper card sorting
            this.nextPlayOrder = 1000; // Start high to avoid conflicts with database play_order
            // Card resolution timing variables
            this.cardResolutionDelay = 800;    // Delay after card is added to resolved area


            // Card tooltips for Kalua (JS format)
            this.CARD_TOOLTIPS = {
                1: { // Global Disaster
                    1: "Lose 3 happiness, convert 1 to atheist, gain 3 prayer.",
                    2: "Lose 3 happiness, convert 1 to atheist, gain 1 prayer.",
                    3: "Lose 2 happiness, 1 family dies, gain 2 prayer.",
                    4: "Lose 2 happiness, convert 1 to atheist, gain 1 prayer.",
                    5: "Lose 2 happiness, convert 1 to atheist, gain 3 prayer.",
                    6: "Convert 1 to atheist, gain 1 prayer.",
                    7: "Lose 1 happiness, convert 1 to atheist, gain 1 prayer.",
                    8: "Convert 1 to atheist, gain 1 prayer, happiness loss: roll d6.",
                    9: "Lose 1 happiness, convert 1 to atheist, prayer gain: roll d6.",
                    10: "Convert 1 to atheist, discard card, gain 1 prayer.",
                },
                2: { // Local Disaster
                    1: "Lose 1 happiness, convert 1 to atheist, 1 family dies. Prayer cost: 4.",
                    2: "Lose 3 happiness, 1 family dies. Prayer cost: 5.",
                    3: "Lose 1 happiness, 1 family dies. Prayer cost: 1.",
                    4: "Lose 2 happiness, 1 family dies. Prayer cost: 3.",
                    5: "Lose 2 happiness, 1 family dies, destroy 1 temple. Prayer cost: 5.",
                },
                3: { // Bonus
                    1: "Gain 2 happiness. Prayer cost: 2.",
                    2: "Gain 4 happiness. Prayer cost: 5.",
                    3: "Gain 1 happiness, convert to religion: roll d6. Prayer cost: 6.",
                    4: "Gain happiness: roll d6. Prayer cost: 5.",
                    5: "Gain 1 happiness, 1 family dies, recover leader. Prayer cost: 5.",
                    6: "Build a temple, keep card. Prayer cost: 5.",
                    7: "Gain amulets, keep card. Prayer cost: 4.",
                }
            };





        },
        setup: function(gamedatas) {
            // Create player areas - current player first, then others
            const sortedPlayers = Object.values(gamedatas.players).sort((a, b) => {
                // Current player goes first
                if (a.id == this.player_id) return -1;
                if (b.id == this.player_id) return 1;
                // Other players maintain their original order
                return parseInt(a.id) - parseInt(b.id);
            });
            sortedPlayers.forEach(player => {
                // Fix player color using helper function
                let fixedColor = this.fixPlayerColor(player.color);
                document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                    <div id="player_area_${player.id}" class="kalua_player_area" ${player.id == this.player_id ? 'data-current-player="true"' : ''}>
                        <div id="player_name_${player.id}" class="kalua_player_name" style="color: ${fixedColor} !important;">${player.name}</div>
                        <div id="${player.id}_cards" class="kalua_player_cards"></div>
                        <div id="${player.id}_families" class="kalua_player_families"></div>
                        <div class="kalua_player_bottom_section">
                            <div id="${player.id}_InPlay" class="kalua_player_kept_cards">
                                KEPT CARDS:
                                <div id="${player.id}_InPlayContent"></div>
                            </div>
                            <div id="${player.id}_player_prayer" class="kalua_player_prayer_tokens">
                                PRAYER:
                                <div id="${player.id}_PrayerContent"></div>
                            </div>
                        </div>
                    </div>
                `);
                // Additional color setting via JavaScript as backup with enhanced safety
                setTimeout(() => {
                    const nameElement = document.getElementById(`player_name_${player.id}`);
                    if (nameElement && fixedColor) {
                        // Apply player color as background with black text
                        nameElement.style.backgroundColor = fixedColor;
                        nameElement.style.color = '#000000';
                        nameElement.style.setProperty('background-color', fixedColor, 'important');
                        nameElement.style.setProperty('color', '#000000', 'important');
                        nameElement.setAttribute('data-player-color', fixedColor);
                        // Also add a CSS class for the color if available
                        const colorClass = this.getPlayerColorClass(fixedColor);
                        if (colorClass) {
                            nameElement.classList.add('kalua_player_name_colored');
                        }
                    } else {
                        console.warn('Could not apply color - nameElement:', !!nameElement, 'fixedColor:', fixedColor);
                    }
                }, 100);
            });
            // Add notice for hidden players preference
            if (this.prefs[100] && this.prefs[100].value == 1) {
                const hiddenCount = Object.keys(gamedatas.players).length - 1;
                if (hiddenCount > 0) {
                    document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                        <div class="kalua_hidden_players_notice">
                            ${hiddenCount} other player${hiddenCount > 1 ? 's' : ''} hidden (preference setting)
                        </div>
                    `);
                }
            }
            // Set up players' side panels
            Object.values(gamedatas.players).forEach(player => {
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', `
                    <div>
                        <span id="icon_p_${player.id}" class="sidebar-icon icon-pray"></span> <span>Prayer: <span id="panel_p_${player.id}"></span><br>
                        <span id="icon_h" class="sidebar-icon icon-happy"></span> <span>Happiness: <span id="panel_h_${player.id}"></span> </span><br>
                        <span id="icon_f" class="sidebar-icon icon-fam1"></span> <span>Family: <span id="panel_f_${player.id}"></span> </span>
                        <span id="icon_l" class="sidebar-icon icon-lead"></span> <span>Leader: <span id="panel_l_${player.id}"></span> </span><br>
                        <span>Cards: <span id="panel_c_${player.id}"></span></span><br>
                        <span>Temples: <span id="panel_t_${player.id}"></span></span>
                        <span>Amulets: <span id="panel_a_${player.id}"></span></span><br>
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
                // Fix: image position should be 0-based (i-1)
                this[`dice`].addItemType(i, i, g_gamethemeurl + 'img/d6_300_246.png', i - 1);
            }
            // Add a die for each player matching their color
            Object.values(gamedatas.players).forEach(player => {
                // Calculate die face: each player's color row starts at (sprite-1)*6 + 1, show face 1 (first face of their color)
                const playerDieFace = ((player.sprite - 1) * 6) + 1;
                // Use player ID as unique die ID so we can update individual dice
                this['dice'].addToStockWithId(playerDieFace, player.id);
            });
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
                // Ensure happiness is within 0-10 range
                const happiness = Math.max(0, Math.min(10, player.happiness || 0));
                // Add the correct token type for this player color
                if (this[`hkToken_${happiness}`]) {
                    this[`hkToken_${happiness}`].addToStock(player.sprite - 1);
                } else {
                    console.error('Missing happiness token stock for happiness level:', happiness, 'player:', player);
                }
            });
            // Create stock for played cards
            this['played'] = new ebg.stock();  
            this['played'].create(this, document.getElementById('playedCardsContent'), 120, 181.3);
            this['played'].image_items_per_row = 5;
            this['played'].setSelectionMode(0);
            // Create stock for resolved cards
            this['resolved'] = new ebg.stock();
            this['resolved'].create(this, document.getElementById('resolvedCardsContent'), 120, 181.3);
            this['resolved'].image_items_per_row = 5;
            this['resolved'].setSelectionMode(0);
            // Initialize card stock for each player card div   
            Object.values(gamedatas.players).forEach(player => {
                // Create cardbacks stock using the cardbacks div
                this[`${player.id}_cardbacks`] = new ebg.stock();
                this[`${player.id}_cardbacks`].create(this, $(`${player.id}_cards`), 120, 180);
                this[`${player.id}_cardbacks`].image_items_per_row = 2;
                this[`${player.id}_cardbacks`].setOverlap(0, 0); // No overlap for grouped cardbacks
                this[`${player.id}_cardbacks`].setSelectionMode(0);
                // Create cards stock using the cards div
                this[`${player.id}_cards`] = new ebg.stock();
                this[`${player.id}_cards`].create(this, $(`${player.id}_cards`), 120, 181.3);
                this[`${player.id}_cards`].image_items_per_row = 5;
                this[`${player.id}_cards`].setOverlap(0, 0); // No overlap for grouped cards
                this[`${player.id}_cards`].setSelectionMode(1); // single selection
                dojo.connect(this[`${player.id}_cards`], 'onChangeSelection', this, 'onPlayerHandSelectionChanged');
                for (let card_id = 80; card_id <= 81; card_id++)
                {              
                    // Add card backs to stock
                    this[`${player.id}_cardbacks`].addItemType(card_id, card_id, g_gamethemeurl + 'img/Cards_Backs_240_180.png', card_id);
                }
                //create amulet/temple stock 1 = amulet, 2 = temple
                this[`${player.id}_kept`] = new ebg.stock();
                this[`${player.id}_kept`].create(this, $(`${player.id}_InPlayContent`), 200, 121);
                this[`${player.id}_kept`].image_items_per_row = 2;
                this[`${player.id}_kept`].setSelectionMode(0);
                for (let kept_id = 1; kept_id <= 2; kept_id++)
                {
                    this[`${player.id}_kept`].addItemType(kept_id, kept_id, g_gamethemeurl + 'img/temple_amulet_400_121.png', kept_id - 1);
                }
                // Initialize prayer token display with simple representation
                // Note: This will be updated in the setup phase with actual player.prayer value
                this.optimizePrayerTokens(player.id, 5);
            });
            /* Add local disaster cards */
            const card_type_local_disaster = this.ID_LOCAL_DISASTER;
            const num_local_disaster_cards = 5;
            for (let card_id = 1; card_id <= num_local_disaster_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_local_disaster, card_id);
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_1632_played.png', card_id - 1);
                // Add to resolved cards stock
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', card_id - 1);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 0 - 4 for local disaster cards */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', card_id - 1);
                });
            }
            /* Add global disaster cards */
            const card_type_global_disaster = this.ID_GLOBAL_DISASTER;
            const num_global_disaster_cards = 10;
            // Global disaster card image position mappings
            // Normal: positions 5-14, Avoid: positions 25-34, Double: positions 35-44
            this.globalDisasterImageMappings = {
                'normal': (card_id) => card_id + 4,    // Original positions 5-14
                'avoid': (card_id) => card_id + 24,    // Avoid positions 25-34  
                'double': (card_id) => card_id + 34    // Double positions 35-44
            };
            for (let card_id = 1; card_id <= num_global_disaster_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_global_disaster, card_id);
                // Start with normal image positions (will be updated when multiplier is selected)
                const normalImagePos = this.globalDisasterImageMappings.normal(card_id);
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_1632_played.png', normalImagePos);
                // Add to resolved cards stock  
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', normalImagePos);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 5 - 14 for normal global disaster cards, will be updated for avoid/double */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', normalImagePos);
                });
            } 
            /* Add bonus cards */
            const card_type_bonus = this.ID_BONUS;
            const num_bonus_cards = 7;
            for (let card_id = 1; card_id <= num_bonus_cards; card_id++)
            {
                const uniqueId = this.getCardUniqueId(card_type_bonus, card_id);
                // Add to played cards stock
                this['played'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_1632_played.png', card_id + 14);
                // Add to resolved cards stock
                this['resolved'].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', card_id + 14);
                Object.values(gamedatas.players).forEach(player => {
                    /* Note: image ID 15-21 for bonus cards */
                    this[`${player.id}_cards`].addItemType(uniqueId, uniqueId, g_gamethemeurl + 'img/Cards_600_907_compressed.png', card_id + 14);
                });
            } 
            /*** Update the UI with gamedata ***/
            /* Update counters */
            Object.values(gamedatas.players).forEach(player => {
                this.updatePlayerPrayer(player.id, player.prayer);
                this.happinessCounters[player.id].setValue(Math.max(0, Math.min(10, player.happiness || 0)));
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
                // Optimize prayer token display for each player's current prayer count
                this.optimizePrayerTokens(player.id, player.prayer);
                // Initialize kept cards based on temple and amulet counts
                if (this[`${player.id}_kept`]) {
                    // Add amulet cards (kept_id = 1)
                    for (let i = 0; i < player.amulet; i++) {
                        this[`${player.id}_kept`].addToStock(1);
                    }
                    // Add temple cards (kept_id = 2)
                    for (let i = 0; i < player.temple; i++) {
                        this[`${player.id}_kept`].addToStock(2);
                    }
                    
                } else {
                    console.error('No kept stock found during initialization for player', player.id);
                }
                /* TODO get each player's hand length to update counters */
                element = $(`panel_l_${player.id}`);
                if (player.chief == 1)
                {
                    element.innerHTML = `<span id="icon_cb_t" class="checkbox-icon icon-check-true"></span>`;
                }
                else
                {
                    element.innerHTML = `<span id="icon_cb_f" class="checkbox-icon icon-check-false"></span>`;
                }
            });
            /* Update player's hands with their drawn cards */
            Object.values(gamedatas.handDisaster).forEach(card => {
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })
            Object.values(gamedatas.handBonus).forEach(card => {
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })
            // Force update display for player card stocks to ensure proper layout
            if (this[`${this.player_id}_cards`]) {
                this[`${this.player_id}_cards`].updateDisplay();
            }
            // Update card grouping for initial hand
            this.updateCardGrouping(this.player_id);
            /* Populate played cards from database */
            Object.values(gamedatas.playedDisaster).forEach(card => {
                
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                // Override the itemType weight with play_order for proper sorting
                if (card.play_order !== undefined) {
                    this['played'].changeItemsWeight(uniqueId, parseInt(card.play_order));
                }
                this['played'].addToStockWithId(uniqueId, card.id);
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by, card.id);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });
            Object.values(gamedatas.playedBonus).forEach(card => {
                
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                // Override the itemType weight with play_order for proper sorting
                if (card.play_order !== undefined) {
                    this['played'].changeItemsWeight(uniqueId, parseInt(card.play_order));
                }
                this['played'].addToStockWithId(uniqueId, card.id);
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by, card.id);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });
            /* Populate resolving cards - these are shown in played area during resolution */
            Object.values(gamedatas.resolvingDisaster).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                
                // For global disasters, update the image position based on multiplier choice
                if (parseInt(card.type) === this.ID_GLOBAL_DISASTER && card.multiplier) {
                    const cardTypeArg = parseInt(card.type_arg);
                    let imagePos;
                    switch(card.multiplier) {
                        case 'avoid':
                            imagePos = this.globalDisasterImageMappings.avoid(cardTypeArg);
                            break;
                        case 'double':
                            imagePos = this.globalDisasterImageMappings.double(cardTypeArg);
                            break;
                        case 'normal':
                        default:
                            imagePos = this.globalDisasterImageMappings.normal(cardTypeArg);
                            break;
                    }
                    // Update the item type's image position before adding the card
                    if (this['played'].item_type && this['played'].item_type[uniqueId]) {
                        this['played'].item_type[uniqueId].image_position = imagePos;
                    }
                }
                
                this['played'].addToStockWithId(uniqueId, card.id);
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by, card.id);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });
            Object.values(gamedatas.resolvingBonus).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['played'].addToStockWithId(uniqueId, card.id);
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by, card.id);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });
            /* Populate resolved cards from database */
            Object.values(gamedatas.resolvedDisaster).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['resolved'].addToStockWithId(uniqueId, card.id);
                this.addCardTooltipByUniqueId('resolved', uniqueId, null, card.id);
            });
            Object.values(gamedatas.resolvedBonus).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['resolved'].addToStockWithId(uniqueId, card.id);
                this.addCardTooltipByUniqueId('resolved', uniqueId, null, card.id);
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
                        // Force update display to ensure proper layout after adding cardbacks
                        cardbackStock.updateDisplay();
                        // Update cardback grouping to show counts
                        this.updateCardbackGrouping(player.id);
                    }
                }
            });
            // Update sidebar counters based on gamedata
            Object.values(gamedatas.players).forEach(player => {
                this.updatePlayerPrayer(player.id, player.prayer);
                this.happinessCounters[player.id].setValue(Math.max(0, Math.min(10, player.happiness || 0)));
                this.cardCounters[player.id].setValue(player.cards);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
                // Optimize prayer token display for each player's current prayer count (redundant but safe)
                this.optimizePrayerTokens(player.id, player.prayer);
            });
            // Set initial round leader prayer icon to grayed version
            if (gamedatas.round_leader) {
                this.updateRoundLeaderIcons(null, gamedatas.round_leader);
            }
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
            // Load dice roll sound effect with explicit path
            try {
                this.sounds.load('dice_roll', 'Dice Roll Sound', g_gamethemeurl + 'img/dice_roll');
            } catch (e) {
                console.warn('Could not load dice roll sound:', e);
            }
            // Force a final layout update for all player card stocks after setup
            setTimeout(() => {
                this.refreshPlayerCardLayouts();
            }, 100);
        },
        ///////////////////////////////////////////////////
        //// Card tooltip functions
        getCardTypeFromUniqueId: function(uniqueId) {
            // Based on getCardUniqueId logic:
            // Global Disaster: uniqueId = type_id (1-10)
            // Local Disaster: uniqueId = 10 + type_id (11-15)  
            // Bonus: uniqueId = 15 + type_id (16-22)
            if (uniqueId >= 1 && uniqueId <= 10) {
                return this.ID_GLOBAL_DISASTER; // 1
            } else if (uniqueId >= 11 && uniqueId <= 15) {
                return this.ID_LOCAL_DISASTER; // 2
            } else if (uniqueId >= 16 && uniqueId <= 22) {
                return this.ID_BONUS; // 3
            }
            return null;
        },
        getCardIdFromUniqueId: function(uniqueId) {
            // Reverse the getCardUniqueId calculation
            if (uniqueId >= 1 && uniqueId <= 10) {
                return uniqueId; // Global disaster type_id
            } else if (uniqueId >= 11 && uniqueId <= 15) {
                return uniqueId - 10; // Local disaster type_id
            } else if (uniqueId >= 16 && uniqueId <= 22) {
                return uniqueId - 15; // Bonus type_id
            }
            return null;
        },
        addCardTooltip: function(elementId, cardType, cardId, playerId = null) {
            // Calculate image position for the larger image
            let imagePosition = 0;
            if (cardType === this.ID_LOCAL_DISASTER) {
                // Local Disaster cards: positions 0-4 (cardId 1-5 maps to positions 0-4)
                imagePosition = cardId - 1;
            } else if (cardType === this.ID_GLOBAL_DISASTER) {
                // Global Disaster cards: check if card has been modified for avoid/double
                // Default to normal position, but this could be updated based on current game state
                imagePosition = cardId + 4; // Normal positions 5-14
                // Note: For tooltips, we'll always show the normal version for consistency
                // The actual played card will show the avoid/double version if selected
            } else if (cardType === this.ID_BONUS) {
                // Bonus cards: positions 15-21 (cardId 1-7 maps to positions 15-21)
                imagePosition = cardId + 14;
            }
            // Calculate X,Y position in sprite grid
            const cardsPerRow = 5;
            const cardWidth = 264.6;   
            const cardHeight = 400; 
            const col = imagePosition % cardsPerRow;
            const row = Math.floor(imagePosition / cardsPerRow);
            const bgPositionX = -(col * cardWidth);
            const bgPositionY = -(row * cardHeight);
            
            // Get tooltip text from CARD_TOOLTIPS
            let tooltipText = "";
            if (this.CARD_TOOLTIPS[cardType] && this.CARD_TOOLTIPS[cardType][cardId]) {
                tooltipText = this.CARD_TOOLTIPS[cardType][cardId];
            }
            
            // Create clean image tooltip without player-specific styling
            const imageUrl = g_gamethemeurl + 'img/Cards_1323_2000_compressed.png';
            const imgElement = `<img src="${imageUrl}" style="width: 262px; height: 400px; object-fit: none; object-position: ${bgPositionX}px ${bgPositionY}px; border: 2px solid #333; border-radius: 8px;" />`;
            
            // Create text region beneath the image
            const textElement = `<div style="width: 262px; padding: 10px; background-color: #f8f8f8; font-size: 14px; line-height: 1.4; color: #333; text-align: left; box-sizing: border-box;">${tooltipText}</div>`;
            
            // Combine image and text
            const fullTooltip = `<div style="display: inline-block;">${imgElement}${textElement}</div>`;
            
            // Add combined tooltip
            this.addTooltipHtml(elementId, fullTooltip, 350);
        },
        addCardTooltipByUniqueId: function(stockName, uniqueId, playerId = null, cardId = null) {
            const cardType = this.getCardTypeFromUniqueId(uniqueId);
            const cardIdFromUnique = this.getCardIdFromUniqueId(uniqueId);
            if (cardType && cardIdFromUnique !== null) {
                // Use BGA stock system's built-in tooltip functionality if available
                setTimeout(() => {
                    // Use provided playerId or try to find it from stored data
                    let finalPlayerId = playerId;
                    if (this[stockName] && this[stockName].getAllItems) {
                        const stockItems = this[stockName].getAllItems();
                        let targetItem = null;
                        
                        // If we have a specific cardId, find that exact item
                        if (cardId !== null) {
                            targetItem = stockItems.find(item => item.id == cardId);
                        } else {
                            // Fallback: find the latest item with this uniqueId that doesn't have a tooltip
                            const itemsWithType = stockItems.filter(item => item.type == uniqueId);
                            for (let i = itemsWithType.length - 1; i >= 0; i--) {
                                const item = itemsWithType[i];
                                const elementId = this[stockName].getItemDivId(item.id);
                                const element = document.getElementById(elementId);
                                if (element && !element.hasAttribute('data-tooltip-added')) {
                                    targetItem = item;
                                    break;
                                }
                            }
                        }
                        
                        if (targetItem) {
                            const elementId = this[stockName].getItemDivId(targetItem.id);
                            // Try to get player ID from stored data if not provided
                            if (!finalPlayerId && this[stockName].items && this[stockName].items[targetItem.id] && this[stockName].items[targetItem.id].played_by) {
                                finalPlayerId = this[stockName].items[targetItem.id].played_by;
                            }
                            if (elementId) {
                                this.addCardTooltip(elementId, cardType, cardIdFromUnique, finalPlayerId);
                                return;
                            }
                        }
                    }
                    // Fallback to previous method
                    this.addTooltipToLatestCard(stockName, uniqueId, cardType, cardIdFromUnique, finalPlayerId);
                }, 300);
            }
        },
        addTooltipToLatestCard: function(stockName, uniqueId, cardType, cardId, playerId = null) {
            // Find elements based on stock type
            let allElements;
            if (stockName.includes('_cards')) {
                // Player card stocks use the standard pattern
                allElements = document.querySelectorAll(`[id^="${stockName}_item_"]`);
            } else if (stockName === 'played' || stockName === 'resolved') {
                // Played/resolved stocks use different patterns
                allElements = document.querySelectorAll(`[id*="${stockName}"] .stockitem, .${stockName} .stockitem, [class*="${stockName}"] .stockitem`);
                if (allElements.length === 0) {
                    // Try broader patterns
                    allElements = document.querySelectorAll(`[id*="${stockName}"][id*="item"], [class*="${stockName}"][class*="item"]`);
                }
            } else {
                // Fallback to standard pattern
                allElements = document.querySelectorAll(`[id^="${stockName}_item_"]`);
            }
            // Find the latest element that doesn't have a tooltip yet
            let targetElement = null;
            for (let i = allElements.length - 1; i >= 0; i--) {
                const element = allElements[i];
                if (!element.hasAttribute('data-tooltip-added')) {
                    targetElement = element;
                    break;
                }
            }
            if (targetElement) {
                const elementId = targetElement.id || `${stockName}_${uniqueId}`;
                this.addCardTooltip(elementId, cardType, cardId, playerId);
                targetElement.setAttribute('data-tooltip-added', 'true');
                targetElement.setAttribute('data-unique-id', uniqueId);
                if (playerId) {
                    targetElement.setAttribute('data-played-by', playerId);
                }
            } else {
            }
        },
        // Function to manually refresh tooltips for all visible cards
        refreshAllCardTooltips: function() {
            // Find all player card areas
            const playerCardAreas = document.querySelectorAll('[id$="_cards_item_"]');
            const playedCardsArea = document.querySelectorAll('[id^="played_item_"]');
            // For each card element, try to determine what it should be
            const allCardElements = [...playerCardAreas, ...playedCardsArea];
            allCardElements.forEach((element) => {
                if (!element.hasAttribute('data-tooltip-added')) {
                    // Add a generic "Card" tooltip for now - better than nothing
                    this.addTooltip(element.id, "Card", "");
                    element.setAttribute('data-tooltip-added', 'true');
                }
            });
        },
        ///////////////////////////////////////////////////
        //// Game & client states
        onEnteringState: function(stateName, args) {
            switch (stateName) {
                case 'phaseOneDraw':
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
                    // Trigger action button setup
                    if (this.isCurrentPlayerActive()) {
                        this.updatePageTitle();
                    }
                    break;
                case 'phaseThreeResolveCard':
                    // This state handles the actual resolution of global disaster cards
                    // No player actions needed, just visual feedback
                    break;
                case 'phaseThreeRollDice':
                    if (this.isCurrentPlayerActive()) {
                        // Reset auto-roll tracking for this new state
                        this.autoRollAttempted = false;
                        // Check if auto-roll dice preference is enabled
                        
                        
                        // Primary method: Check CSS class (BGA preference system)
                        let autoRollEnabled = document.body.classList.contains('kalua_auto_dice');
                        if (!autoRollEnabled) {
                            // Fallback: Check preference objects (for debugging)
                            if (this.prefs && this.prefs[101]) {
                                if (this.prefs[101].value == 2) {
                                    autoRollEnabled = true;
                                }
                            } else if (this.player_preferences && this.player_preferences[101]) {
                                if (this.player_preferences[101].value == 2) {
                                    autoRollEnabled = true;
                                }
                            }
                        }
                        
                        if (autoRollEnabled && !this.autoRollAttempted) {
                            // Auto-roll dice preference is enabled
                            this.autoRollAttempted = true;
                            // Use immediate execution
                            try {
                                this.bgaPerformAction('actRollDie', {});
                            } catch (error) {
                                console.error('Auto-roll action failed:', error);
                                // Reset flag so onUpdateActionButtons can try
                                this.autoRollAttempted = false;
                                // Fall back to manual mode
                                this.setupDiceRoll();
                            }
                        } else if (!autoRollEnabled) {
                            // Manual dice rolling
                            this.setupDiceRoll();
                        } else {
                        }
                    }
                    break;
                case 'phaseThreeDiscard':
                    if (this.isCurrentPlayerActive()) {
                        this.setupDiscard();
                    }
                    break;
                case 'phaseThreePlayCard':
                    // No special action needed
                    break;
                case 'phaseFourConvertPray':
                    // No special action needed
                    break;
                default:
                    // Perform actions for unknown state
                    break;
            }
            // Refresh player card layouts on any state change to prevent layout issues
            setTimeout(() => {
                this.refreshPlayerCardLayouts();
            }, 50);
        },
        onLeavingState: function(stateName) {
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
                    // Clean up any state-specific elements when leaving phaseOneDraw
                    // Do not add action buttons here - buttons should only be added in onUpdateActionButtons
                    break;
                default:
                    // Perform actions for unknown state
                    break;
            }
            // Perform actions specific to leaving a state
        }, 
        onUpdateActionButtons: function(stateName, args) {
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
                        // Check if player should be automatically passed
                        const cardsInHand = this[`${this.player_id}_cards`].count();
                        const currentPrayer = this.prayerCounters[this.player_id].getValue();
                        if (cardsInHand === 0 && currentPrayer < 5) {
                            // Player has no cards and insufficient prayer to buy more - automatically pass
                            this.showMessage(_('You have no cards and insufficient prayer to buy more. Automatically passing your turn.'), 'info');
                            // Automatically trigger the pass action after a brief delay to show the message
                            setTimeout(() => {
                                this.bgaPerformAction('actPlayCardPass', {});
                            }, 2000);
                            // Still show buttons but disable them to indicate automatic pass
                            this.addActionButton('playCard-btn', _('Play Card'), () => {}, 'red');
                            dojo.addClass('playCard-btn', 'disabled');
                            this.addActionButton('buyCardReflex-btn', _('Buy Card (5 Prayer)'), () => {}, 'red');
                            dojo.addClass('buyCardReflex-btn', 'disabled');
                            this.addActionButton('pass-btn', _('Passing Automatically...'), () => {}, 'gray');
                            dojo.addClass('pass-btn', 'disabled');
                            return; // Skip normal button setup
                        }
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
                        // Show pass button for all players
                        this.addActionButton('pass-btn', _('Pass'), () => {
                            this.bgaPerformAction('actPlayCardPass', {});
                        });
                        // Only show convert button for the round leader, and only enable it if they can convert
                        if (this.player_id == this.gamedatas.round_leader) {
                            this.addActionButton('convert-btn', _('CONVERT! (End Card Phase)'), () => {
                                this.bgaPerformAction('actSayConvert', {});
                            });
                            // Use the args from the state to determine if convert is allowed
                            const canConvert = args && args.can_convert;
                            if (!canConvert) {
                                dojo.addClass('convert-btn', 'disabled');
                            }
                            // Use the args from the state to determine if pass is allowed
                            const canPass = args && args.can_pass;
                            if (!canPass) {
                                // Use setTimeout to ensure the button is fully rendered before disabling
                                setTimeout(() => {
                                    const passBtn = document.getElementById('pass-btn');
                                    if (passBtn) {
                                        dojo.addClass(passBtn, 'disabled');
                                    }
                                }, 10);
                            }
                        } else {
                            // For non-round leaders, no special logic needed - they can always pass
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
                    case 'phaseThreeRollDice':
                        // Check if auto-roll is enabled using CSS class (primary method)
                        let isAutoRoll = document.body.classList.contains('kalua_auto_dice');
                        if (!isAutoRoll) {
                            // Fallback: Check preference objects
                            
                            if (this.prefs && this.prefs[101] && this.prefs[101].value == 2) {
                                isAutoRoll = true;
                            } else if (this.player_preferences && this.player_preferences[101] && this.player_preferences[101].value == 2) {
                                isAutoRoll = true;
                            }
                        }
                        // TRIGGER AUTO-ROLL HERE (when UI is fully ready) if not already attempted
                        if (isAutoRoll && this.isCurrentPlayerActive() && !this.autoRollAttempted) {
                            this.autoRollAttempted = true;
                            // Use a small delay to ensure the action buttons are fully setup
                            setTimeout(() => {
                                if (this.gamedatas.gamestate.name === 'phaseThreeRollDice' && this.isCurrentPlayerActive()) {
                                    try {
                                        this.bgaPerformAction('actRollDie', {});
                                    } catch (error) {
                                        console.error('Auto-roll action failed in onUpdateActionButtons:', error);
                                    }
                                } else {
                                }
                            }, 50); // Very short delay just to ensure buttons are ready
                        } else if (isAutoRoll && this.autoRollAttempted) {
                        } else if (!isAutoRoll && this.isCurrentPlayerActive() && !this.autoRollAttempted) {
                            // Set up a backup checker for auto-roll in case preferences load later
                            let checkCount = 0;
                            const maxChecks = 10;
                            const checkInterval = setInterval(() => {
                                checkCount++;
                                const hasAutoClass = document.body.classList.contains('kalua_auto_dice');
                                if (hasAutoClass && !this.autoRollAttempted && this.gamedatas.gamestate.name === 'phaseThreeRollDice' && this.isCurrentPlayerActive()) {
                                    this.autoRollAttempted = true;
                                    clearInterval(checkInterval);
                                    try {
                                        this.bgaPerformAction('actRollDie', {});
                                    } catch (error) {
                                        console.error('Auto-roll action failed in backup checker:', error);
                                    }
                                } else if (checkCount >= maxChecks || this.gamedatas.gamestate.name !== 'phaseThreeRollDice' || !this.isCurrentPlayerActive()) {
                                    clearInterval(checkInterval);
                                }
                            }, 200); // Check every 200ms
                        }
                        const buttonText = isAutoRoll ? _('Roll Dice (Auto)') : _('Roll Dice');
                        this.addActionButton('roll-dice-btn', buttonText, () => {
                            // Only allow manual rolling if auto-roll is disabled
                            if (!isAutoRoll) {
                                // Disable the button immediately to prevent double-clicks
                                const rollBtn = document.getElementById('roll-dice-btn');
                                if (rollBtn) {
                                    rollBtn.disabled = true;
                                    rollBtn.style.opacity = '0.5';
                                }
                                this.bgaPerformAction('actRollDie', {});
                            } else {
                            }
                        });
                        // If auto-roll is enabled, disable the button and style it as inactive
                        if (isAutoRoll) {
                            const rollBtn = document.getElementById('roll-dice-btn');
                            if (rollBtn) {
                                rollBtn.disabled = true;
                                rollBtn.style.backgroundColor = '#cccccc';
                                rollBtn.style.color = '#666666';
                                rollBtn.style.opacity = '0.6';
                                rollBtn.style.cursor = 'not-allowed';
                                rollBtn.title = _('Dice will roll automatically (auto-roll enabled in preferences)');
                            }
                        }
                        break;
                    case 'phaseThreeResolveAmulets':
                        // Note: Do not call updatePageTitle() here as it causes infinite recursion
                        // The page title will be updated by the BGA framework automatically
                        // Check if current player has amulets
                        const currentPlayerId = this.player_id;
                        const currentPlayerAmulets = this.gamedatas.players[currentPlayerId]?.amulet || 0;
                        if (currentPlayerAmulets > 0) {
                            this.addActionButton('use-amulet-btn', _('Use Amulet'), () => {
                                this.disableAmuletButtons();
                                this.bgaPerformAction('actAmuletChoose', { use_amulet: true });
                            });
                            this.addActionButton('no-amulet-btn', _('Do Not Use Amulet'), () => {
                                this.disableAmuletButtons();
                                this.bgaPerformAction('actAmuletChoose', { use_amulet: false });
                            });
                        } else {
                            // Player has no amulets, just waiting
                            // Note: Do not call updatePageTitle() here as it causes infinite recursion
                            // The page title will be updated by the BGA framework automatically
                        }
                        break;
                }
            }
        },
        ///////////////////////////////////////////////////
        //// Utility methods
        ///////////////////////////////////////////////////
        //// Force layout refresh for player card areas
        refreshPlayerCardLayouts: function() {
            // Refresh all player card stocks to ensure proper layout
            if (this.gamedatas && this.gamedatas.players) {
                Object.values(this.gamedatas.players).forEach(player => {
                    if (this[`${player.id}_cards`]) {
                        this[`${player.id}_cards`].updateDisplay();
                    }
                    if (this[`${player.id}_cardbacks`]) {
                        this[`${player.id}_cardbacks`].updateDisplay();
                    }
                    if (this[`${player.id}_kept`]) {
                        this[`${player.id}_kept`].updateDisplay();
                    }
                });
            }
        },
        //// Prayer token management helper function
        updatePlayerPrayer: function(playerId, newPrayerValue) {
            if (this.prayerCounters[playerId]) {
                this.prayerCounters[playerId].setValue(newPrayerValue);
                this.optimizePrayerTokens(playerId, newPrayerValue);
            } else {
                console.warn(`No prayer counter found for player ${playerId}`);
            }
        },
        ///////////////////////////////////////////////////
        //// Prayer token optimization helper
        optimizePrayerTokens: function(playerId, targetCount) {
            const prayerContent = document.getElementById(`${playerId}_PrayerContent`);
            if (!prayerContent) {
                console.warn(`No prayer content found for player ${playerId}`);
                return;
            }
            
            // Clear existing content
            prayerContent.innerHTML = '';
            
            // Calculate groups of 5 and singles
            const fiveGroups = Math.floor(targetCount / 5);
            const singles = targetCount % 5;
            
            // Add group of 5 icon with multiplier if needed
            if (fiveGroups > 0) {
                const groupDiv = document.createElement('div');
                groupDiv.className = 'prayer_token_group';
                
                const groupIcon = document.createElement('div');
                groupIcon.className = 'prayer_token_icon group';
                
                const groupMultiplier = document.createElement('div');
                groupMultiplier.className = 'prayer_token_multiplier';
                groupMultiplier.textContent = `×${fiveGroups}`;
                
                groupDiv.appendChild(groupIcon);
                groupDiv.appendChild(groupMultiplier);
                prayerContent.appendChild(groupDiv);
            }
            
            // Add single icon with multiplier if needed
            if (singles > 0) {
                const singleDiv = document.createElement('div');
                singleDiv.className = 'prayer_token_group';
                
                const singleIcon = document.createElement('div');
                singleIcon.className = 'prayer_token_icon single';
                
                const singleMultiplier = document.createElement('div');
                singleMultiplier.className = 'prayer_token_multiplier';
                singleMultiplier.textContent = `×${singles}`;
                
                singleDiv.appendChild(singleIcon);
                singleDiv.appendChild(singleMultiplier);
                prayerContent.appendChild(singleDiv);
            }
            
            // If no tokens at all, show empty state
            if (targetCount === 0) {
                prayerContent.innerHTML = '<div style="opacity: 0.4; font-size: 14px; color: #999;">0</div>';
            }
        },
        ///////////////////////////////////////////////////
        //// Global disaster card image switching
        updateGlobalDisasterCardImage: function(cardId, cardTypeArg, multiplierChoice) {
            // Get the unique ID for this global disaster card
            const uniqueId = this.getCardUniqueId(this.ID_GLOBAL_DISASTER, cardTypeArg);
            // Determine the new image position based on multiplier choice
            let newImagePos;
            switch(multiplierChoice) {
                case 'avoid':
                    newImagePos = this.globalDisasterImageMappings.avoid(cardTypeArg);
                    break;
                case 'double':
                    newImagePos = this.globalDisasterImageMappings.double(cardTypeArg);
                    break;
                case 'normal':
                default:
                    newImagePos = this.globalDisasterImageMappings.normal(cardTypeArg);
                    break;
            }
            
            console.log(`Updating card ${cardId} (type_arg ${cardTypeArg}) to ${multiplierChoice}: position ${newImagePos}`);
            
            // Update the card image in the played cards stock
            if (this['played'] && this['played'].item_type && this['played'].item_type[uniqueId]) {
                // Store original played_by data before removing
                let playedBy = null;
                if (this['played'].items && this['played'].items[cardId]) {
                    playedBy = this['played'].items[cardId].played_by;
                }
                
                this['played'].item_type[uniqueId].image_position = newImagePos;
                
                // Force a visual update of the specific card if it exists in the played stock
                const playedItems = this['played'].getAllItems();
                const targetItem = playedItems.find(item => item.id == cardId);
                if (targetItem) {
                    // Remove and re-add the item to force visual update
                    this['played'].removeFromStockById(cardId);
                    this['played'].addToStockWithId(uniqueId, cardId);
                    
                    // Restore played_by data
                    if (playedBy && this['played'].items && this['played'].items[cardId]) {
                        this['played'].items[cardId].played_by = playedBy;
                    }
                    
                    // Re-add tooltip and player border
                    setTimeout(() => {
                        this.addCardTooltipByUniqueId('played', uniqueId, playedBy, cardId);
                        if (playedBy) {
                            this.addPlayerBorderToCard(cardId, playedBy, 'played');
                        }
                    }, 100);
                }
            }
            // Update in resolved cards stock as well (in case it moves there later)
            if (this['resolved'] && this['resolved'].item_type && this['resolved'].item_type[uniqueId]) {
                this['resolved'].item_type[uniqueId].image_position = newImagePos;
            }
        },
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
            /* TODO exception? */
            return 0;
        },
        playCardOnTable : function(player_id, color, value, card_id) {
            
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                    this[`${player_id}_cards`].removeFromStockById(card_id);
                    if (this.cardCounters[player_id]) {
                        const currentValue = this.cardCounters[player_id].getValue();
                        this.cardCounters[player_id].setValue(Math.max(0, currentValue - 1));
                    }
                    // Update card grouping after removing card
                    this.updateCardGrouping(player_id);
            // Add card to played cards area
            const uniqueId = this.getCardUniqueId(parseInt(color), parseInt(value)); // Generate unique ID
            this['played'].addToStockWithId(uniqueId, card_id); // Add card to played cards area  
            // Store player info for this card for future reference
            if (this['played'] && this['played'].items && this['played'].items[card_id]) {
                this['played'].items[card_id].played_by = player_id;
            }
            // Add tooltip with player information
            setTimeout(() => {
                const cardType = this.getCardTypeFromUniqueId(uniqueId);
                const cardIdFromUnique = this.getCardIdFromUniqueId(uniqueId);
                if (cardType && cardIdFromUnique !== null) {
                    // Find the DOM element for this card
                    const stockItems = this['played'].getAllItems();
                    
                    const targetItem = Object.values(stockItems).find(item => item.type == uniqueId);
                    if (targetItem) {
                        const elementId = this['played'].getItemDivId(targetItem.id);
                        if (elementId) {
                            // Add tooltip with player information
                            this.addCardTooltip(elementId, cardType, cardIdFromUnique, player_id);
                        } else {

                        }
                    } else {

                        // Try alternative approach - find by card_id directly
                        const fallbackElementId = `played_item_${card_id}`;
                        this.addCardTooltip(fallbackElementId, cardType, cardIdFromUnique, player_id);
                    }
                } else {

                }
                // Add visual player indicator
                this.addPlayerIndicator(card_id, player_id, 'played');
            }, 200);
        },
        // Add a simple colored indicator next to the card
        addPlayerIndicator: function(card_id, player_id, stock_name) {
            try {
                // Get player color
                let playerColor = '#4685FF'; // default blue
                let playerName = 'Unknown';
                if (this.gamedatas && this.gamedatas.players && this.gamedatas.players[player_id]) {
                    playerColor = this.gamedatas.players[player_id].color;
                    playerName = this.gamedatas.players[player_id].name;
                    // Apply the same color fixing logic using helper function
                    const originalColor = playerColor;
                    playerColor = this.fixPlayerColor(playerColor);
                    if (playerColor !== originalColor) {
                    }
                }
                setTimeout(() => {
                    // Find the card element
                    const selectors = [
                        `#${stock_name}_item_${card_id}`,
                        `[id="${stock_name}_item_${card_id}"]`,
                        `[id*="${stock_name}"][id*="item_${card_id}"]`
                    ];
                    let cardElement = null;
                    for (let selector of selectors) {
                        cardElement = document.querySelector(selector);
                        if (cardElement) break;
                    }
                    if (cardElement) {
                        // Remove any existing indicator
                        const existingIndicator = cardElement.querySelector('.player-indicator');
                        if (existingIndicator) {
                            existingIndicator.remove();
                        }
                        // Create player indicator
                        const indicator = document.createElement('div');
                        indicator.className = 'player-indicator';
                        indicator.style.cssText = `
                            position: absolute;
                            top: -5px;
                            right: -5px;
                            width: 20px;
                            height: 20px;
                            background-color: ${playerColor};
                            border: 2px solid white;
                            border-radius: 50%;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                            z-index: 10;
                            cursor: help;
                        `;
                        indicator.title = `Played by ${playerName}`;
                        // Ensure the card element has relative positioning
                        if (getComputedStyle(cardElement).position === 'static') {
                            cardElement.style.position = 'relative';
                        }
                        cardElement.appendChild(indicator);
                        
                    } else {
                        console.warn(`Could not find card element for ${stock_name}_item_${card_id}`);
                    }
                }, 150);
            } catch (error) {
                console.error('Error in addPlayerIndicator:', error);
            }
        },
        // Helper function to add player color border to cards
        addPlayerBorderToCard: function(card_id, player_id, stock_name) {
            if (!player_id || !this.gamedatas.players[player_id]) {
                console.warn(`No player data found for player ${player_id}`);
                return;
            }
            // Get player's fixed color using helper function
            let fixedColor = this.gamedatas.players[player_id].color;
            const originalColor = fixedColor;
            fixedColor = this.fixPlayerColor(fixedColor);
            if (fixedColor !== originalColor) {
            } else {
            }
            // Apply CSS class using BGA Stock extraClasses functionality
            const colorClass = this.getPlayerColorClass(fixedColor);
            if (!colorClass) {
                console.warn(`No color class found for color ${fixedColor}`);
                return;
            }
            if (this[stock_name]) {
                // Use setTimeout to ensure the stock item exists, with retry logic
                const attemptBorderApplication = (attempt = 1, maxAttempts = 5) => {
                    setTimeout(() => {
                        let applied = false;
                        // Method 1: Try BGA Stock addExtraClass method
                        try {
                            if (this[stock_name].addExtraClass) {
                                this[stock_name].addExtraClass(card_id, colorClass);
                                
                                applied = true;
                            }
                        } catch (e) {
                            console.warn('addExtraClass method failed:', e);
                        }
                        // Method 2: Try multiple DOM selector approaches
                        if (!applied) {
                            const selectors = [
                                `#${stock_name}_item_${card_id}`,
                                `[id="${stock_name}_item_${card_id}"]`,
                                `[id*="${stock_name}"][id*="item_${card_id}"]`,
                                `[id*="${card_id}"]`
                            ];
                            for (let selector of selectors) {
                                const cardElement = document.querySelector(selector);
                                if (cardElement) {
                                    cardElement.classList.add(colorClass);
                                    
                                    applied = true;
                                    break;
                                }
                            }
                        }
                        // Method 3: Try finding by stock item class and data attributes
                        if (!applied) {
                            const stockElements = document.querySelectorAll(`.stockitem[data-card-id="${card_id}"], .stockitem[id*="${card_id}"]`);
                            if (stockElements.length > 0) {
                                stockElements.forEach(element => {
                                    element.classList.add(colorClass);
                                    
                                });
                                applied = true;
                            }
                        }
                        if (!applied && attempt < maxAttempts) {
                            
                            attemptBorderApplication(attempt + 1, maxAttempts);
                        } else if (!applied) {
                            console.error(`Could not find or apply color class to card ${card_id} in stock ${stock_name} after ${maxAttempts} attempts`);
                            // Log all possible elements for debugging
                            const allElements = document.querySelectorAll(`[id*="${card_id}"]`);
                        }
                    }, attempt * 100); // Increase delay with each attempt
                };
                attemptBorderApplication();
            } else {
                console.warn(`Stock ${stock_name} not found`);
            }
        },
        // Helper function to get CSS class name based on player color
        getPlayerColorClass: function(color) {
            const colorClassMap = {
                '#4685FF': 'kalua_player_card_blue',
                '#C22D2D': 'kalua_player_card_red',
                '#C8CA25': 'kalua_player_card_yellow',
                '#2EA232': 'kalua_player_card_green',
                '#913CB3': 'kalua_player_card_purple'
            };
            return colorClassMap[color] || null;
        },
        // Helper function to fix truncated player colors consistently
        fixPlayerColor: function(color) {
            if (!color) {
                return '#4685FF'; // Default to blue if no color
            }
            // Ensure it starts with #
            if (!color.startsWith('#')) {
                color = '#' + color;
            }
            // If color is truncated (length 6), fix it
            if (color.length === 6) {
                const colorMap = {
                    '#4685F': '#4685FF', // Blue
                    '#C22D2': '#C22D2D', // Red
                    '#C8CA2': '#C8CA25', // Yellow
                    '#2EA23': '#2EA232', // Green
                    '#913CB': '#913CB3'  // Purple
                };
                const fixedColor = colorMap[color];
                if (fixedColor) {
                    return fixedColor;
                }
            }
            // If color is already complete (length 7), just return it
            if (color.length === 7) {
                return color;
            }
            // For any other case, try to map it to a known color
            const allColors = ['#4685FF', '#C22D2D', '#C8CA25', '#2EA232', '#913CB3'];
            console.warn(`Unknown color format: ${color}, defaulting to blue`);
            return '#4685FF'; // Default to blue
        },
        // Helper function to convert hex color to rgba
        hexToRgba: function(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        },
        drawCard: function(player, card_id, card_type, card_type_arg) {
            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg)); // Generate unique ID
            this[`${player}_cards`].addToStockWithId(uniqueId, card_id); // Add card to player's hand
            this.addCardTooltipByUniqueId(`${player}_cards`, uniqueId, null, card_id); // Add tooltip
            // Force layout update to ensure cards display properly
            if (this[`${player}_cards`]) {
                this[`${player}_cards`].updateDisplay();
            }
            // Update card grouping to show counts
            this.updateCardGrouping(player);
        },
        ///////////////////////////////////////////////////
        //// Card grouping and count overlay functions
        updateCardGrouping: function(player_id) {
            setTimeout(() => {
                const cardStock = this[`${player_id}_cards`];
                if (!cardStock || !cardStock.items) {
                    console.log('No card stock or items for player', player_id);
                    return;
                }
                
                console.log('Updating card grouping for player', player_id);
                
                // Group cards by type and track which one to keep visible
                const cardsByType = {};
                const allItems = Object.values(cardStock.items);
                
                allItems.forEach(item => {
                    const uniqueId = item.type;
                    if (!cardsByType[uniqueId]) {
                        cardsByType[uniqueId] = [];
                    }
                    cardsByType[uniqueId].push(item);
                });
                
                console.log('Cards grouped by type:', cardsByType);
                
                // Store hidden cards to restore later
                if (!this.hiddenCardsByPlayer) {
                    this.hiddenCardsByPlayer = {};
                }
                if (!this.hiddenCardsByPlayer[player_id]) {
                    this.hiddenCardsByPlayer[player_id] = [];
                }
                
                // Clear previous hidden cards tracking
                this.hiddenCardsByPlayer[player_id] = [];
                
                // For each type, show only the first card with a counter, hide the rest
                Object.keys(cardsByType).forEach(uniqueId => {
                    const cardsOfType = cardsByType[uniqueId];
                    const count = cardsOfType.length;
                    
                    cardsOfType.forEach((item, index) => {
                        const cardElementId = cardStock.getItemDivId(item.id);
                        const cardElement = document.getElementById(cardElementId);
                        
                        if (cardElement) {
                            if (index === 0) {
                                // First card of this type - show it with counter if count > 1
                                cardElement.classList.remove('card-hidden-duplicate');
                                
                                if (count > 1) {
                                    console.log('Adding count', count, 'to first card', item.id, 'of type', uniqueId);
                                    this.addCardCountOverlay(cardElement, count);
                                    cardElement.classList.add('has-card-count');
                                } else {
                                    this.removeCardCountOverlay(cardElement);
                                    cardElement.classList.remove('has-card-count');
                                }
                            } else {
                                // Duplicate card - completely hide it
                                console.log('Hiding duplicate card', item.id, 'of type', uniqueId);
                                cardElement.classList.add('card-hidden-duplicate');
                                cardElement.style.display = 'none';
                                this.removeCardCountOverlay(cardElement);
                                cardElement.classList.remove('has-card-count');
                                // Track this as hidden
                                this.hiddenCardsByPlayer[player_id].push(item.id);
                            }
                        }
                    });
                });
                
                // Force the stock to recalculate layout without hidden cards
                if (cardStock.updateDisplay) {
                    cardStock.updateDisplay();
                }
                
                // Force a reflow to recalculate container height
                const cardContainer = document.getElementById(`${player_id}_cards`);
                if (cardContainer) {
                    void cardContainer.offsetHeight;
                    const parentContainer = cardContainer.parentElement;
                    if (parentContainer) {
                        void parentContainer.offsetHeight;
                    }
                }
            }, 150);
        },
        updateCardbackGrouping: function(player_id) {
            setTimeout(() => {
                const cardbackStock = this[`${player_id}_cardbacks`];
                if (!cardbackStock || !cardbackStock.items) {
                    console.log(`No cardback stock or items for player ${player_id}`);
                    return;
                }
                
                console.log(`Updating cardback grouping for player ${player_id}`);
                
                // Group cardbacks by type
                const cardbacksByType = {};
                const allItems = Object.values(cardbackStock.items);
                
                allItems.forEach(item => {
                    const uniqueId = item.type;
                    if (!cardbacksByType[uniqueId]) {
                        cardbacksByType[uniqueId] = [];
                    }
                    cardbacksByType[uniqueId].push(item);
                });
                
                console.log(`Cardbacks grouped by type for player ${player_id}:`, cardbacksByType);
                
                // Store hidden cardbacks to restore later if needed
                if (!this.hiddenCardbacksByPlayer) {
                    this.hiddenCardbacksByPlayer = {};
                }
                if (!this.hiddenCardbacksByPlayer[player_id]) {
                    this.hiddenCardbacksByPlayer[player_id] = [];
                }
                
                // Clear previous hidden cardbacks tracking
                this.hiddenCardbacksByPlayer[player_id] = [];
                
                // For each type, show only the first cardback with a counter, hide the rest
                Object.keys(cardbacksByType).forEach(uniqueId => {
                    const cardbacksOfType = cardbacksByType[uniqueId];
                    const count = cardbacksOfType.length;
                    
                    cardbacksOfType.forEach((item, index) => {
                        const cardbackElementId = cardbackStock.getItemDivId(item.id);
                        const cardbackElement = document.getElementById(cardbackElementId);
                        
                        if (cardbackElement) {
                            if (index === 0) {
                                // First cardback of this type - show it with counter if count > 1
                                cardbackElement.classList.remove('card-hidden-duplicate');
                                cardbackElement.style.display = '';
                                
                                if (count > 1) {
                                    this.addCardCountOverlay(cardbackElement, count);
                                    cardbackElement.classList.add('has-card-count');
                                } else {
                                    this.removeCardCountOverlay(cardbackElement);
                                    cardbackElement.classList.remove('has-card-count');
                                }
                            } else {
                                // Duplicate cardback - completely hide it
                                cardbackElement.classList.add('card-hidden-duplicate');
                                cardbackElement.style.display = 'none';
                                this.removeCardCountOverlay(cardbackElement);
                                cardbackElement.classList.remove('has-card-count');
                                
                                // Track hidden cardbacks
                                this.hiddenCardbacksByPlayer[player_id].push(item.id);
                            }
                        }
                    });
                });
                
                // Force the stock to recalculate layout without hidden cards
                if (cardbackStock.updateDisplay) {
                    cardbackStock.updateDisplay();
                }
                
                // Force a reflow to recalculate container height
                // This fixes the issue where the div doesn't resize until window resize
                const cardbackContainer = document.getElementById(`${player_id}_cardbacks`);
                if (cardbackContainer) {
                    // Trigger reflow by reading offsetHeight
                    void cardbackContainer.offsetHeight;
                    // Force the parent container to recalculate as well
                    const parentContainer = cardbackContainer.parentElement;
                    if (parentContainer) {
                        void parentContainer.offsetHeight;
                    }
                }
            }, 150);
        },
        addCardCountOverlay: function(cardElement, count) {
            // Remove existing overlay if present
            this.removeCardCountOverlay(cardElement);
            
            // Ensure card has relative positioning for absolute overlay
            if (getComputedStyle(cardElement).position === 'static') {
                cardElement.style.position = 'relative';
            }
            
            // Create count overlay badge
            const overlay = document.createElement('div');
            overlay.className = 'card-count-overlay';
            overlay.textContent = `×${count}`;
            cardElement.appendChild(overlay);
        },
        removeCardCountOverlay: function(cardElement) {
            const existingOverlay = cardElement.querySelector('.card-count-overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
        },
        movetokens: function(tokenTypeToMove, desiredShift) {
            let flag = false;
            for (let x = 0; x <= 10; x++) {
                const tokens = this[`hkToken_${x}`].items;
                Object.values(tokens).forEach(token => {
                    if (token.type == tokenTypeToMove && flag == false) {
                        // Calculate target position
                        let newdiv = x + desiredShift;
                        if (newdiv < 0) newdiv = 0;
                        if (newdiv > 10) newdiv = 10;
                        
                        // Store token info before removal
                        const tokenId = token.id;
                        const tokenType = token.type;
                        
                        // Add delay to make token movement visible
                        setTimeout(() => {
                            // First remove the token by ID (not by type)
                            this[`hkToken_${x}`].removeFromStockById(tokenId);
                            
                            // Add to adjacent stock with slight additional delay
                            setTimeout(() => {
                                this[`hkToken_${newdiv}`].addToStockWithId(tokenType, tokenId);
                            }, this.hkTokenTransferDelay);
                        }, this.hkTokenMoveDelay);
                        
                        flag = true; // only move one token
                    }
                });
            }
        },
        giveSpeech: function(player_id) {
            const currentValue = this.happinessCounters[player_id].getValue();
            const newValue = Math.max(0, Math.min(10, currentValue + 1));
            this.happinessCounters[player_id].setValue(newValue); // Increase happiness by 1, clamped to 0-10
            // Use the player's sprite value from gamedatas to move the correct token
            const sprite = this.gamedatas.players[player_id].sprite;
            this.movetokens(sprite-1, 1);
        },
        convertAtheists: function(player_id, num_atheists) {
            const atheistFamilies = this['atheists'];
            const playerFamilies = this[`fams_${player_id}`];
            for (let i = 0; i < num_atheists; i++) {
                atheistFamilies.removeFromStock(this.ID_AHTHIEST_STOCK); // Remove from atheist families
                playerFamilies.addToStock(this.ID_AHTHIEST_STOCK); // Add to player's families
            }
            this.familyCounters[player_id].incValue(num_atheists);
        },
        setupTargetSelection: function() {
            /* Present other players as target options */
            this.gamedatas.gamestate.descriptionmyturn = _('Choose a player to target with your disaster: ');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            // Get all players except the current player
            const otherPlayers = Object.values(this.gamedatas.players).filter(player => player.id != this.player_id);
            otherPlayers.forEach(player => {
                this.addActionButton(`target-player-${player.id}`, player.name, () => {
                    this.bgaPerformAction('actSelectPlayer', { player_id: player.id });
                });
            });
        },
        chooseConvertTarget: function() {
            /* Present the option of the other players to the user as buttons */
            this.gamedatas.gamestate.descriptionmyturn = _('Choose a player to convert from: ');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            const otherPlayers = Object.values(this.gamedatas.players).filter(player => player.id != this.player_id);
            otherPlayers.forEach(player =>
            {
                /* TODO disable button if no families? but what if no one else has families? warning? */
                button = this.statusBar.addActionButton(player.name, () => 
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
            /* Present amulet usage choice to the player */
            this.gamedatas.gamestate.descriptionmyturn = _('Do you want to use an amulet to avoid the disaster effects?');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            this.addActionButton('use-amulet-btn', _('Use Amulet'), () => {
                this.disableAmuletButtons();
                this.bgaPerformAction('actAmuletChoose', { use_amulet: true });
            });
            this.addActionButton('no-amulet-btn', _('Do Not Use Amulet'), () => {
                this.disableAmuletButtons();
                this.bgaPerformAction('actAmuletChoose', { use_amulet: false });
            });
        },
        disableAmuletButtons: function() {
            // Disable buttons to prevent double-clicks and show feedback
            const useBtn = document.getElementById('use-amulet-btn');
            const noBtn = document.getElementById('no-amulet-btn');
            if (useBtn) {
                useBtn.disabled = true;
                useBtn.style.opacity = '0.5';
            }
            if (noBtn) {
                noBtn.disabled = true;
                noBtn.style.opacity = '0.5';
            }
        },
        setupDiceRoll: function() {
            /* Present dice rolling option to the player */
            // Note: Buttons are now handled in onUpdateActionButtons method
            // This function can be used for other setup if needed in the future
        },
        setupDiscard: function() {
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
            element.innerHTML = `<span id="icon_cb_f" class="checkbox-icon icon-check-false"></span>`;
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
            // automatically listen to the notifications, based on the `notif_xxx` function on this class.
            this.bgaSetupPromiseNotifications();
            // Add 0.5 second delays to card resolution notifications so players can follow along
            this.notifqueue.setSynchronous('playerCountsChanged', 500);
            this.notifqueue.setSynchronous('familiesConverted', 500);
            this.notifqueue.setSynchronous('familiesDied', 500);
            this.notifqueue.setSynchronous('templeDestroyed', 500);
            this.notifqueue.setSynchronous('leaderRecovered', 500);
            this.notifqueue.setSynchronous('templeBuilt', 500);
            this.notifqueue.setSynchronous('amuletGained', 500);
            this.notifqueue.setSynchronous('cardResolved', 500); // Reduced to prevent timeout
            // cardBeingResolved removed - it's now a no-op and doesn't need delay
            this.notifqueue.setSynchronous('diceRolled', 500);
            this.notifqueue.setSynchronous('amuletUsed', 500);
            this.notifqueue.setSynchronous('amuletNotUsed', 500);
            // Add tooltips to any cards that might have been missed
            setTimeout(() => {
                this.refreshAllCardTooltips();
            }, 1000);
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
                // Check for warnings if warnings preference is enabled
                this.checkCardWarnings(selectedCards[0]);
            } else {
                if ($('playCard-btn')) {
                    dojo.addClass('playCard-btn', 'disabled');
                }
                if ($('discard-btn')) {
                    dojo.addClass('discard-btn', 'disabled');
                }
            }
        },
        // Check if the selected card would be ineffective and show warnings
        checkCardWarnings: function(card) {
            // Only show warnings if the preference is enabled
            if (!this.isWarningsEnabled()) {
                return;
            }
            const cardType = parseInt(card.type);
            const cardTypeArg = parseInt(card.type_arg);
            // Check for Recover Leader card when player already has a leader
            if (cardType === this.ID_BONUS && cardTypeArg === 4) { // BonusCard::NewLeader = 4
                const currentPlayer = this.gamedatas.players[this.player_id];
                if (currentPlayer && currentPlayer.chief > 0) {
                    this.showMessage(_('Warning: You already have a leader. This Recover Leader card will have no effect.'), 'info');
                    return;
                }
            }
            // Check for Temple Destroyed card when no temples exist
            if (cardType === this.ID_LOCAL_DISASTER && cardTypeArg === 5) { // LocalDisasterCard::TempleDestroyed = 5
                let anyPlayerHasTemples = false;
                Object.values(this.gamedatas.players).forEach(player => {
                    if (player.temple > 0) {
                        anyPlayerHasTemples = true;
                    }
                });
                // Also check if temple cards are in play (played or resolving)
                let templeCardsInPlay = false;
                if (this['played'] && this['played'].items) {
                    Object.values(this['played'].items).forEach(item => {
                        const uniqueId = parseInt(item.type);
                        const cardInfo = this.getCardInfoFromUniqueId(uniqueId);
                        if (cardInfo.type === this.ID_BONUS && cardInfo.type_arg === 7) { // BonusCard::Temple = 7
                            templeCardsInPlay = true;
                        }
                    });
                }
                if (this['resolved'] && this['resolved'].items) {
                    Object.values(this['resolved'].items).forEach(item => {
                        const uniqueId = parseInt(item.type);
                        const cardInfo = this.getCardInfoFromUniqueId(uniqueId);
                        if (cardInfo.type === this.ID_BONUS && cardInfo.type_arg === 7) { // BonusCard::Temple = 7
                            templeCardsInPlay = true;
                        }
                    });
                }
                if (!anyPlayerHasTemples && !templeCardsInPlay) {
                    this.showMessage(_('Warning: No player has temples and no temple cards are in play. This Temple Destroyed card will have no effect.'), 'info');
                    return;
                }
            }
            // Additional warning for global disasters with no targets
            if (cardType === this.ID_GLOBAL_DISASTER) {
                let anyPlayerHasFamilies = false;
                Object.values(this.gamedatas.players).forEach(player => {
                    if (player.family > 0) {
                        anyPlayerHasFamilies = true;
                    }
                });
                // Check specific global disasters that affect families
                const familyAffectingCards = [1, 2, 3, 4, 5, 6, 7, 8, 9]; // Most global disasters affect families
                if (familyAffectingCards.includes(cardTypeArg) && !anyPlayerHasFamilies) {
                    this.showMessage(_('Warning: No players have families. This global disaster will have reduced impact.'), 'info');
                    return;
                }
            }
        },
        // Check if warnings preference is enabled
        isWarningsEnabled: function() {
            // Check if warnings are enabled via CSS class (preference system)
            return dojo.hasClass('ebd-body', 'kalua_warnings_on');
        },
        // Helper function to get card info from unique ID
        getCardInfoFromUniqueId: function(uniqueId) {
            // Reverse the logic from getCardUniqueId
            if (uniqueId >= 3001 && uniqueId <= 3007) {
                return { type: this.ID_BONUS, type_arg: uniqueId - 3000 };
            } else if (uniqueId >= 2001 && uniqueId <= 2005) {
                return { type: this.ID_LOCAL_DISASTER, type_arg: uniqueId - 2000 };
            } else if (uniqueId >= 1001 && uniqueId <= 1010) {
                return { type: this.ID_GLOBAL_DISASTER, type_arg: uniqueId - 1000 };
            }
            return { type: 0, type_arg: 0 };
        },
        notif_playerDrewCard: async function( args )
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const type = args.card_type;
            const card_id = args.card_id;
            if (player_id == this.player_id)
            {
                this.drawCard(player_id, args.card_id, args.card_type, args.card_type_arg);
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
                    // Update cardback grouping after adding
                    this.updateCardbackGrouping(player_id);
                }
            }
            /* Update counter with protection against negative values */
            if (this.cardCounters[player_id]) {
                const currentValue = this.cardCounters[player_id].getValue();
                this.cardCounters[player_id].setValue(Math.max(0, currentValue + 1));
            }
        },
        notif_quickstartCardsDealt: async function( args )
        {
            // Force refresh all player hands to show the newly dealt cards
            const players = args.players;
            for (let player_id of players) {
                if (player_id == this.player_id) {
                    // For current player, trigger a full hand refresh
                    this.updateDisplay();
                }
            }
        },
        notif_giveSpeech: async function( args )
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            this.giveSpeech(player_id);
        },
        notif_convertAtheists: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const num_atheists = args.num_atheists;
            this.convertAtheists(player_id, num_atheists);
            // Update prayer token display if prayer value is provided
            if (args.prayer !== undefined) {
                this.updatePlayerPrayer(args.player_id, args.prayer);
            }
        },
        notif_sacrificeLeader: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const num_atheists = args.num_atheists;
            const player_no = args.player_no;
            this.sacrificeLeader(player_id, player_no, num_atheists);
        },
        notif_convertBelievers: async function(args)
        {
            const player_id = args.player_id;
            const player_name = args.player_name;
            const target_id = args.target_id;
            const target_name = args.target_name;
            this.convertBelievers(player_id, target_id);
            // Update prayer token display if prayer value is provided for either player
            if (args.prayer !== undefined) {
                this.updatePlayerPrayer(args.player_id, args.prayer);
            }
            if (args.target_prayer !== undefined) {
                this.updatePlayerPrayer(args.target_id, args.target_prayer);
            }
        },
        notif_cardPlayed: function(args) {
            // Remove the card from the correct player's hand if the stock exists
            const playerCardsStock = this[`${args.player_id}_cards`];
            if (playerCardsStock) {
                playerCardsStock.removeFromStockById(args.card_id);
                // Update card grouping after removing card
                this.updateCardGrouping(args.player_id);
            }
            // Remove the cardback from other players' view
            const cardbackStock = this[`${args.player_id}_cardbacks`];
            if (cardbackStock) {
                cardbackStock.removeFromStockById(args.card_id);
                // Update cardback grouping after removing
                this.updateCardbackGrouping(args.player_id);
            }
            // Update card counter with exact count from server (prevents negative values)
            if (this.cardCounters[args.player_id] && args.card_count !== undefined) {
                this.cardCounters[args.player_id].setValue(Math.max(0, args.card_count));
            }
            // Add the card to the played stock
            const uniqueId = this.getCardUniqueId(parseInt(args.card_type), parseInt(args.card_type_arg));
            if (this['played']) {
                // Set proper weight for play order sorting
                this['played'].changeItemsWeight(uniqueId, this.nextPlayOrder);
                this.nextPlayOrder++; // Increment for next card
                
                this['played'].addToStockWithId(uniqueId, args.card_id);
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[args.card_id]) {
                    this['played'].items[args.card_id].played_by = args.player_id;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, args.player_id, args.card_id);
                // Add player color border to the played card
                this.addPlayerBorderToCard(args.card_id, args.player_id, 'played');
            }
            // Update prayer counter if prayer was spent
            if (args.new_prayer_total !== undefined && this.prayerCounters[args.player_id]) {
                this.prayerCounters[args.player_id].setValue(args.new_prayer_total);
            }
        },
        notif_cardBought: function(args) {
            // Update card counter for the player who bought a card
            if (this.cardCounters[args.player_id]) {
                const currentValue = this.cardCounters[args.player_id].getValue();
                this.cardCounters[args.player_id].setValue(Math.max(0, currentValue + 1));
            }
        },
        notif_cardDrawn: function(args) {
            // Private notification - add the card to the player's hand if it's the current player
            if (args.player_id == this.player_id && args.card) {
                const card = args.card;
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                const playerCardsStock = this[`${this.player_id}_cards`];
                if (playerCardsStock) {
                    playerCardsStock.addToStockWithId(uniqueId, card.id);
                    this.addCardTooltipByUniqueId(`${this.player_id}_cards`, uniqueId, null, card.id);
                    // Update card grouping after adding card
                    this.updateCardGrouping(this.player_id);
                }
            }
        },
        notif_prayerSpent: function(args) {
            // Update prayer counter and tokens for the player who spent prayer points
            this.updatePlayerPrayer(args.player_id, args.new_prayer_total);
        },
        notif_globalDisasterChoice: function(args) {
            // Update the global disaster card image based on the multiplier choice
            if (args.card_id && args.card_type_arg && args.choice) {
                this.updateGlobalDisasterCardImage(args.card_id, args.card_type_arg, args.choice);
            }
        },
        notif_roundLeaderChanged: function(args) {
            // Update the round leader in gamedatas
            this.gamedatas.round_leader = args.player_id;
            // Update prayer icons when round leader changes
            this.updateRoundLeaderIcons(args.old_leader, args.player_id);
            // Display prominent message in sidebar about round leader change
            const playerName = args.player_name;
            this.showMessage(dojo.string.substitute(_('${player_name} is now the round leader'), {
                player_name: playerName
            }), 'info');
        },
        notif_initialRoundLeader: function(args) {
            // Display message about initial round leader
            const playerName = args.player_name;
            this.showMessage(dojo.string.substitute(_('${player_name} will lead the first round'), {
                player_name: playerName
            }), 'info');
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
        },
        notif_roundLeaderTurnStart: function(args) {
            // Update the game state data for round leader
            this.gamedatas.round_leader_played_card = args.round_leader_played_card || 0;
            // If round leader hasn't played a card yet and this player is the round leader
            if (this.gamedatas.round_leader_played_card === 0 && this.player_id == this.gamedatas.round_leader) {
                // Disable pass button
                setTimeout(() => {
                    const passBtn = document.getElementById('pass-btn');
                    if (passBtn && !dojo.hasClass(passBtn, 'disabled')) {
                        dojo.addClass(passBtn, 'disabled');
                    }
                }, 50);
                // Re-enable convert button if it exists and is currently disabled
                setTimeout(() => {
                    const convertBtn = document.getElementById('convert-btn');
                    if (convertBtn && dojo.hasClass(convertBtn, 'disabled')) {
                        dojo.removeClass(convertBtn, 'disabled');
                    }
                }, 50);
            }
        },
        updateRoundLeaderIcons: function(old_leader, new_leader) {
            // Reset old leader's prayer icon to normal
            if (old_leader) {
                const oldIcon = document.getElementById(`icon_p_${old_leader}`);
                if (oldIcon) {
                    oldIcon.className = 'sidebar-icon icon-pray';
                }
            }
            // Set new leader's prayer icon to grayed version
            if (new_leader) {
                const newIcon = document.getElementById(`icon_p_${new_leader}`);
                if (newIcon) {
                    newIcon.className = 'sidebar-icon icon-pray_g';
                }
            }
        },
        notif_targetSelected: function(args) {
            // The target selection is complete, the game will continue with card resolution
            // No specific UI updates needed here as the game will transition to the next state
        },
        notif_amuletDecision: function(args) {
            // Store which players have amulets for reference
            this.playersWithAmulets = args.players_with_amulets || [];
            // Show message about who needs to make decisions
            const playersWithAmuletsNames = this.playersWithAmulets.map(playerId => 
                this.gamedatas.players[playerId]?.name || 'Unknown'
            ).join(', ');
            if (this.playersWithAmulets.length > 0) {
                this.showMessage(dojo.string.substitute(_('Players with amulets deciding: ${players}'), {
                    players: playersWithAmuletsNames
                }), 'info');
            }
        },
        notif_amuletPhaseSkipped: function(args) {
            // Just a notification that the amulet phase was skipped
        },
        notif_amuletUsed: function(args) {
            const player_name = args.player_name;
            const player_id = args.player_id;
            
            // Update gamedata
            if (this.gamedatas.players[player_id]) {
                this.gamedatas.players[player_id].amulet = Math.max(0, (this.gamedatas.players[player_id].amulet || 0) - 1);
            }
            
            // Decrement amulet counter
            if (this.amuletCounters[player_id]) {
                this.amuletCounters[player_id].incValue(-1);
            }
            
            // Remove one amulet from the kept cards display
            if (this[`${player_id}_kept`]) {
                const keptItems = this[`${player_id}_kept`].getAllItems();
                // Find an amulet item (type 1 = amulet, type 2 = temple)
                const amuletItem = keptItems.find(item => item.type === 1);
                if (amuletItem) {
                    this[`${player_id}_kept`].removeFromStockById(amuletItem.id);
                }
            }
        },
        notif_amuletNotUsed: function(args) {
            const player_name = args.player_name;
            // Visual feedback that player declined to use amulet
            // Visual feedback could be added here
        },
        notif_amuletProtection: function(args) {
            const player_name = args.player_name;
            const player_id = args.player_id;
            // Visual feedback could be added here to show amulet protection
            // For example, a brief animation or highlighting of the player's board
        },
        notif_diceRollRequired: function(args) {
            // Players who need to roll dice will be prompted in the setupDiceRoll method
        },
        notif_diceRolled: function(args) {
            const player_name = args.player_name;
            const player_id = args.player_id;
            const result = args.result;
            // Play dice roll sound effect
            this.sounds.play('dice_roll');
            // Wait 0.2 seconds before updating dice graphics
            setTimeout(() => {
                // Reset auto-roll flag when any player rolls dice
                // This ensures each client is ready for the next dice roll state
                this.autoRollAttempted = false;
                
                // Update only the specific player's die
                if (player_id && this.gamedatas.players[player_id]) {
                    const player = this.gamedatas.players[player_id];
                    // Calculate which dice face to show: player's color row + rolled result
                    const playerDieFace = ((player.sprite - 1) * 6) + result;
                    // Remove the old die for this player and add the new result
                    this['dice'].removeFromStockById(player_id);
                    this['dice'].addToStockWithId(playerDieFace, player_id);
                }
            }, 200); // 0.2 seconds delay
            this.disableNextMoveSound();
        },
        notif_templeIncremented: function(args) {
            const player_id = args.player_id;
            // Update temple counter with exact count from server
            if (this.templeCounters[player_id]) {
                if (args.temple_count !== undefined) {
                    this.templeCounters[player_id].setValue(args.temple_count);
                } else {
                    this.templeCounters[player_id].incValue(1);
                }
            }
            // Add temple card to kept area (kept_id = 2 for temple, based on comment in setup)
            const keptStock = this[`${player_id}_kept`];
            if (keptStock) {
                
                keptStock.addToStock(2);
                
            } else {
                console.error('No kept stock found for player', player_id);
            }
        },
        notif_amuletIncremented: function(args) {
            const player_id = args.player_id;
            // Update amulet counter
            if (this.amuletCounters[player_id]) {
                this.amuletCounters[player_id].incValue(1);
            }
            // Add amulet card to kept area (kept_id = 1 for amulet, based on comment in setup)
            const keptStock = this[`${player_id}_kept`];
            if (keptStock) {
                
                keptStock.addToStock(1);
                
            } else {
                console.error('No kept stock found for player', player_id);
            }
        },
        notif_templeDestroyed: function(args) {
            const player_id = args.player_id;
            const temples_destroyed = args.temples_count;
            // Update temple counter
            if (this.templeCounters[player_id] && args.temples_remaining !== undefined) {
                this.templeCounters[player_id].setValue(args.temples_remaining);
            }
            // Remove temple cards from kept area (kept_id = 2 for temple)
            const keptStock = this[`${player_id}_kept`];
            if (keptStock && temples_destroyed > 0) {
                for (let i = 0; i < temples_destroyed; i++) {
                    // Remove one temple item from stock
                    const templeItems = keptStock.getAllItems().filter(item => item.type == 2);
                    if (templeItems.length > 0) {
                        keptStock.removeFromStockById(templeItems[0].id);
                    }
                }
                
            }
        },
        notif_templeBonus: function(args) {
            // Just a notification message - the prayer counter will be updated by playerCountsChanged
            // Update prayer token display if prayer value is provided
            if (args.prayer !== undefined && args.player_id) {
                this.updatePlayerPrayer(args.player_id, args.prayer);
            }
        },
        notif_playerCountsChanged: function(args) {
            const player_id = args.player_id;
            // Update all player counters
            if (this.familyCounters[player_id]) {
                const currentFamilyCount = this.familyCounters[player_id].getValue();
                const newFamilyCount = args.family_count;
                const familyChange = newFamilyCount - currentFamilyCount;
                this.familyCounters[player_id].setValue(newFamilyCount);
                // Update family stock (visual meeples) if there's a change
                if (familyChange !== 0 && this[`fams_${player_id}`]) {
                    if (familyChange > 0) {
                        // Add family meeples
                        for (let i = 0; i < familyChange; i++) {
                            this[`fams_${player_id}`].addToStock(this.ID_AHTHIEST_STOCK);
                        }
                    } else {
                        // Remove family meeples
                        const meeplesToRemove = Math.abs(familyChange);
                        for (let i = 0; i < meeplesToRemove; i++) {
                            // Check if we have family meeples to remove
                            const currentFamilyMeeples = this[`fams_${player_id}`].getItemNumber(this.ID_AHTHIEST_STOCK);
                            if (currentFamilyMeeples > 0) {
                                this[`fams_${player_id}`].removeFromStock(this.ID_AHTHIEST_STOCK);
                            }
                        }
                    }
                }
            }
            if (this.prayerCounters[player_id]) {
                this.updatePlayerPrayer(player_id, args.prayer);
            }
            if (this.happinessCounters[player_id] && args.happiness !== undefined) {
                const currentHappiness = this.happinessCounters[player_id].getValue();
                const newHappiness = Math.max(0, Math.min(10, args.happiness || 0));
                const happinessChange = newHappiness - currentHappiness;
                this.happinessCounters[player_id].setValue(newHappiness);
                // Move happiness token if there's a change
                if (happinessChange !== 0 && this.gamedatas.players[player_id]) {
                    const sprite = this.gamedatas.players[player_id].sprite;
                    this.movetokens(sprite - 1, happinessChange);
                }
            }
            if (this.templeCounters[player_id] && args.temple_count !== undefined) {
                this.templeCounters[player_id].setValue(args.temple_count);
            }
        },
        notif_leaderRecovered: function(args) {
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
                leaderElement.innerHTML = `<span id="icon_cb_t" class="checkbox-icon icon-check-true"></span>`;
            }
        },
        notif_cardDiscarded: function(args) {
            const player_id = args.player_id;
            const card_id = args.card_id;
            // Remove the card from the player's hand if the stock exists
            const playerCardsStock = this[`${player_id}_cards`];
            if (playerCardsStock) {
                playerCardsStock.removeFromStockById(card_id);
                // Update card grouping after removing card
                this.updateCardGrouping(player_id);
            }
            // Remove the cardback from other players' view
            const cardbackStock = this[`${player_id}_cardbacks`];
            if (cardbackStock) {
                cardbackStock.removeFromStockById(card_id);
                // Update cardback grouping after removing
                this.updateCardbackGrouping(player_id);
            }
            // Update card counter (prevent negative values)
            if (this.cardCounters[player_id]) {
                const currentValue = this.cardCounters[player_id].getValue();
                this.cardCounters[player_id].setValue(Math.max(0, currentValue - 1));
            }
        },
        notif_cardResolved: async function(args) {
            const card_id = args.card_id;
            const card_type = args.card_type;
            const card_type_arg = args.card_type_arg;
            
            console.log(`Resolving card ${card_id} (type ${card_type}, type_arg ${card_type_arg})`);
            
            // Move card from played stock to resolved stock with animation
            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg));
            
            if (this['played'] && this['resolved']) {
                // Check if card exists in played stock
                const playedItems = this['played'].getAllItems();
                const cardInPlayed = playedItems.find(item => item.id == card_id);
                
                if (!cardInPlayed) {
                    console.warn(`Card ${card_id} not found in played stock`);
                    return;
                }
                
                // Get source element before adding to destination
                const sourceElement = document.getElementById(`played_item_${card_id}`);
                
                if (!sourceElement) {
                    console.warn(`Source element not found for card ${card_id}`);
                    // Just move without animation
                    this['played'].removeFromStockById(card_id);
                    this['resolved'].addToStockWithId(uniqueId, card_id);
                    this.addCardTooltipByUniqueId('resolved', uniqueId, null, card_id);
                    await new Promise(resolve => setTimeout(resolve, 700));
                    return;
                }
                
                // Add to resolved stock
                this['resolved'].addToStockWithId(uniqueId, card_id);
                
                // Get destination element
                const destElement = document.getElementById(`resolved_item_${card_id}`);
                
                // Animate if both elements exist
                if (destElement) {
                    // Use BGA's slideToObject for smooth animation
                    const destRect = destElement.getBoundingClientRect();
                    const sourceRect = sourceElement.getBoundingClientRect();
                    
                    // Temporarily position the destination at the source location
                    destElement.style.transform = `translate(${sourceRect.left - destRect.left}px, ${sourceRect.top - destRect.top}px)`;
                    destElement.style.transition = 'none';
                    
                    // Force reflow
                    destElement.offsetHeight;
                    
                    // Animate to final position
                    setTimeout(() => {
                        destElement.style.transition = 'transform 600ms ease-in-out';
                        destElement.style.transform = 'translate(0, 0)';
                    }, 50);
                    
                    // Remove from played stock after animation starts
                    setTimeout(() => {
                        if (this['played']) {
                            this['played'].removeFromStockById(card_id);
                        }
                    }, 100);
                } else {
                    console.warn(`Destination element not found for card ${card_id}`);
                    // Remove from played immediately if no animation
                    this['played'].removeFromStockById(card_id);
                }
                
                // Add tooltip
                this.addCardTooltipByUniqueId('resolved', uniqueId, null, card_id);
                
                // Wait for animation to complete before proceeding to next notification
                await new Promise(resolve => setTimeout(resolve, 700));
            }
        },
        notif_cardBeingResolved: function(args) {
            // This notification is sent before cardResolved
            // We don't remove the card here - let cardResolved handle it with animation
            // Just log for debugging if needed
        },
        notif_cardResolutionComplete: function(args) {
            // Notification that all cards have been resolved
            // No action needed, just acknowledge
            console.log("Card resolution complete");
        },
        notif_resolvedCardsCleanup: function(args) {
            // Clear all cards from the resolved stock
            if (this['resolved']) {
                this['resolved'].removeAll();
            }
        },
        notif_allCardsCleanup: function(args) {
            // Clear all cards from both played and resolved stocks
            if (this['played']) {
                this['played'].removeAll();
            }
            if (this['resolved']) {
                this['resolved'].removeAll();
            }
        },
        notif_familiesConverted: function(args) {
            // Update family counter for the affected player
            if (this.familyCounters[args.player_id]) {
                this.familyCounters[args.player_id].setValue(args.families_remaining);
            }
            
            // Visually handle family conversion in the meeple display
            const playerFamilies = this[`fams_${args.player_id}`];
            if (playerFamilies && args.families_count > 0) {
                // Remove regular family meeples (NEVER remove chief meeple)
                for (let i = 0; i < args.families_count; i++) {
                    // Only remove atheist-type meeples (ID_AHTHIEST_STOCK = 5)
                    // Chief meeples have IDs 0-4 (player.sprite - 1) and should never be removed here
                    if (playerFamilies.count() > 0) {
                        const items = playerFamilies.getAllItems();
                        // Find a regular family meeple (not the chief) to remove
                        const regularMeeple = items.find(item => item.type === this.ID_AHTHIEST_STOCK);
                        if (regularMeeple) {
                            playerFamilies.removeFromStockById(regularMeeple.id);
                        }
                    }
                }
            }
            
            // Add converted families to atheist stock
            for (let i = 0; i < args.families_count; i++) {
                this['atheists'].addToStock(this.ID_AHTHIEST_STOCK); // 5 = atheist meeple
            }
            // Update prayer token display if prayer value is provided
            if (args.prayer !== undefined) {
                this.updatePlayerPrayer(args.player_id, args.prayer);
            }
        },
        notif_familiesDied: function(args) {
            // Update family counter for the affected player
            if (this.familyCounters[args.player_id]) {
                this.familyCounters[args.player_id].setValue(args.families_remaining);
            }
            
            // Visually handle family death in the meeple display
            const playerFamilies = this[`fams_${args.player_id}`];
            if (playerFamilies && args.families_count > 0) {
                // Remove regular family meeples (NEVER remove chief meeple)
                for (let i = 0; i < args.families_count; i++) {
                    // Only remove atheist-type meeples (ID_AHTHIEST_STOCK = 5)  
                    // Chief meeples have IDs 0-4 (player.sprite - 1) and should never be removed here
                    if (playerFamilies.count() > 0) {
                        const items = playerFamilies.getAllItems();
                        // Find a regular family meeple (not the chief) to remove
                        const regularMeeple = items.find(item => item.type === this.ID_AHTHIEST_STOCK);
                        if (regularMeeple) {
                            playerFamilies.removeFromStockById(regularMeeple.id);
                        }
                    }
                }
            }
            // Note: Dead families don't go to atheist pool, they just disappear
        },
    });
});
