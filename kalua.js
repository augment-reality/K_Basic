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
            console.log('kalua constructor');

            // Setup non-player based divs
            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="board_background">
                    <div id="hkboard"></div>
                    <div id="atheistFamilies"></div>
                    <div id="dice"></div>
                </div>

                <div id="card_areas">
                
                    <div id="playedCards">
                        <div class="played_resolved">Played Cards:</div>
                        <div id="playedCardsContent"></div>
                    </div>
                    <div id="resolvedCards" style="position: relative;">
                        <div class="played_resolved">Resolved Cards:</div>
                        <div id="resolvedCardsContent"></div>
                        <div id="prediction_panel" style="display: none; position: absolute; top: 0; left: 100%; margin-left: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 1000; max-width: 250px; min-width: 200px;">
                            <div id="prediction_panel_header" style="font-weight: bold; margin-bottom: 5px; cursor: pointer; user-select: none;">
                                ⭐ Convert/Pray Predictions ✖
                            </div>
                            <div id="prediction_content"></div>
                            <div style="font-size: 10px; margin-top: 5px; opacity: 0.7;">
                                Based on current happiness & temples
                            </div>
                        </div>
                    </div>
                </div>

                <div id="player-tables" class="zone-container"></div>
                
                <!-- Dev Statistics Display -->
                <div id="dev_stats_panel" style="position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.9); color: white; padding: 15px; border-radius: 8px; font-size: 24px; z-index: 2000; max-width: 600px; max-height: 600px; overflow-y: auto; display: none;">
                    <div style="font-weight: bold; margin-bottom: 10px; cursor: pointer; user-select: none; font-size: 24px;" onclick="this.parentElement.style.display='none'">
                        📊 Live Game Statistics ✖
                    </div>
                    <div style="font-weight: bold; color: #4CAF50; margin-bottom: 5px; font-size: 21px;">Table Statistics:</div>
                    <div id="table_stats_content"></div>
                    <div style="font-weight: bold; color: #2196F3; margin: 10px 0 5px 0; font-size: 21px;">Player Statistics:</div>
                    <div id="player_stats_content"></div>
                    <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #444; font-size: 18px; opacity: 0.7;">
                        Updates automatically • For development only
                    </div>
                </div>
                
                <!-- Dev Statistics Toggle Button -->
                <div id="dev_stats_toggle" style="position: fixed; top: 10px; right: 10px; background: #FF9800; color: white; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-size: 18px; font-weight: bold; z-index: 1999;" onclick="document.getElementById('dev_stats_panel').style.display='block'; this.style.display='none';">
                    📊 Stats
                </div>
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
            
            // Prediction panel settings
            this.predictionPanelEnabled = false;
            
            // Auto-roll tracking to prevent multiple attempts
            this.autoRollAttempted = false;
        },
        
        setup: function(gamedatas) {
            console.log("Starting game setup");
            
            // Create player areas - current player first, then others
            const sortedPlayers = Object.values(gamedatas.players).sort((a, b) => {
                // Current player goes first
                if (a.id == this.player_id) return -1;
                if (b.id == this.player_id) return 1;
                // Other players maintain their original order
                return parseInt(a.id) - parseInt(b.id);
            });
            
            sortedPlayers.forEach(player => {
                console.log('Player color debug:', player.id, player.color); // Debug line
                
                // Fix player color using helper function
                let fixedColor = this.fixPlayerColor(player.color);
                if (fixedColor !== player.color) {
                    console.log('Fixed truncated color from', player.color, 'to', fixedColor);
                } else {
                    console.log('Color already complete:', fixedColor);
                }
                
                document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                    <div id="player_area_${player.id}" class="player_area" ${player.id == this.player_id ? 'data-current-player="true"' : ''}>
                        <div id="player_name_${player.id}" class="player_name" style="color: ${fixedColor} !important;">${player.name}</div>
                        <div id="${player.id}_cards" class="player_cards"></div>
                        <div id="${player.id}_families" class="player_families"></div>
                        <div class="player_bottom_section">
                            <div id="${player.id}_InPlay" class="player_kept_cards">
                                Kept Cards:
                                <div id="${player.id}_InPlayContent"></div>
                            </div>
                            <div id="${player.id}_player_prayer" class="player_prayer_tokens">
                                Prayer:
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
                            nameElement.classList.add('player-name-colored');
                        }
                        console.log('Applied background color to', player.name, ':', fixedColor);
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
                        <div class="hidden_players_notice">
                            ${hiddenCount} other player${hiddenCount > 1 ? 's' : ''} hidden (preference setting)
                        </div>
                    `);
                }
            }

            // Set up players' side panels
            Object.values(gamedatas.players).forEach(player => {
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', `
                    <div>
                        <span id="icon_p_${player.id}" class="icon_p" style="display:inline-block;vertical-align:middle;"></span></span> <span>Prayer: <span id="panel_p_${player.id}"></span><br>
                        <span id="icon_h" style="display:inline-block;vertical-align:middle;"></span> <span>Happiness: <span id="panel_h_${player.id}"></span> </span><br>
                        <span id="icon_f" style="display:inline-block;vertical-align:middle;"></span> <span>Family: <span id="panel_f_${player.id}"></span> </span>
                        <span>Leader: <span id="panel_l_${player.id}"></span> </span><br>
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
                this[`${player.id}_cardbacks`].create(this, $(`${player.id}_cards`), 120, 174);
                this[`${player.id}_cardbacks`].image_items_per_row = 2;
                this[`${player.id}_cardbacks`].setSelectionMode(0);

                // Create cards stock using the cards div
                this[`${player.id}_cards`] = new ebg.stock();
                this[`${player.id}_cards`].create(this, $(`${player.id}_cards`), 120, 181.3);
                this[`${player.id}_cards`].image_items_per_row = 5;
                this[`${player.id}_cards`].setSelectionMode(1); // single selection
                dojo.connect(this[`${player.id}_cards`], 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

                for (let card_id = 80; card_id <= 81; card_id++)
                {              
                    // Add card backs to stock
                    this[`${player.id}_cardbacks`].addItemType(card_id, card_id, g_gamethemeurl + 'img/Cards_Backs_240_174.png', card_id);
                }

                //create amulet/temple stock 1 = amulet, 2 = temple
                this[`${player.id}_kept`] = new ebg.stock();
                this[`${player.id}_kept`].create(this, $(`${player.id}_InPlayContent`), 118.5, 76);
                this[`${player.id}_kept`].image_items_per_row = 2;
                this[`${player.id}_kept`].setSelectionMode(0);
                for (let kept_id = 1; kept_id <= 2; kept_id++)
                {
                    this[`${player.id}_kept`].addItemType(kept_id, kept_id, g_gamethemeurl + 'img/temple_amulet_237_76.png', kept_id);
                }
                console.log('Created kept stock for player', player.id, ':', this[`${player.id}_kept`]);

                //create prayer token zone for organic pile arrangement
                this[`${player.id}_prayer_zone`] = new ebg.zone();
                this[`${player.id}_prayer_zone`].create(this, `${player.id}_PrayerContent`, 30, 30);
                
                // Configure zone for organic pile-like arrangement
                this[`${player.id}_prayer_zone`].item_margin = 2;
                this[`${player.id}_prayer_zone`].instantaneous = false; // Enable animations
                this[`${player.id}_prayer_zone`].control_name = 'prayer_tokens';
                
                // Override the default positioning for more organic feel
                this[`${player.id}_prayer_zone`].getAllItems = function() {
                    return this.items;
                };
                
                console.log('Created prayer token zone for player', player.id, ':', this[`${player.id}_prayer_zone`]);

                // Initialize with 5 prayer tokens using optimized representation
                // Note: This will be optimized again in the setup phase with actual player.prayer value
                this.optimizePrayerTokens(player.id, 5);
                console.log('Initialized prayer tokens for player', player.id);

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
                    console.log(`Initializing kept cards for player ${player.id}: amulets=${player.amulet}, temples=${player.temple}`);
                    // Add amulet cards (kept_id = 1)
                    for (let i = 0; i < player.amulet; i++) {
                        this[`${player.id}_kept`].addToStock(1);
                    }
                    // Add temple cards (kept_id = 2)
                    for (let i = 0; i < player.temple; i++) {
                        this[`${player.id}_kept`].addToStock(2);
                    }
                    console.log('Initialized kept stock items:', this[`${player.id}_kept`].getAllItems());
                } else {
                    console.error('No kept stock found during initialization for player', player.id);
                }
                
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
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })

            Object.values(gamedatas.handBonus).forEach(card => {
                this.drawCard(this.player_id, card.id, card.type, card.type_arg);
            })

            /* Populate played cards from database */
            console.log(`DEBUG: gamedatas.playedDisaster:`, gamedatas.playedDisaster);
            Object.values(gamedatas.playedDisaster).forEach(card => {
                console.log(`DEBUG: Processing disaster card:`, card);
                console.log(`DEBUG: card.played_by = ${card.played_by} (type: ${typeof card.played_by})`);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['played'].addToStockWithId(uniqueId, card.id);
                
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });

            console.log(`DEBUG: gamedatas.playedBonus:`, gamedatas.playedBonus);
            Object.values(gamedatas.playedBonus).forEach(card => {
                console.log(`DEBUG: Processing bonus card:`, card);
                console.log(`DEBUG: card.played_by = ${card.played_by} (type: ${typeof card.played_by})`);
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['played'].addToStockWithId(uniqueId, card.id);
                
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[card.id]) {
                    this['played'].items[card.id].played_by = card.played_by;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, card.played_by);
                this.addPlayerBorderToCard(card.id, card.played_by, 'played');
            });

            /* Populate resolved cards from database */
            Object.values(gamedatas.resolvedDisaster).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['resolved'].addToStockWithId(uniqueId, card.id);
                this.addCardTooltipByUniqueId('resolved', uniqueId);
            });

            Object.values(gamedatas.resolvedBonus).forEach(card => {
                const uniqueId = this.getCardUniqueId(parseInt(card.type), parseInt(card.type_arg));
                this['resolved'].addToStockWithId(uniqueId, card.id);
                this.addCardTooltipByUniqueId('resolved', uniqueId);
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
                this.updatePlayerPrayer(player.id, player.prayer);
                this.happinessCounters[player.id].setValue(Math.max(0, Math.min(10, player.happiness || 0)));
                this.cardCounters[player.id].setValue(player.cards);
                this.templeCounters[player.id].setValue(player.temple);
                this.amuletCounters[player.id].setValue(player.amulet);
                this.familyCounters[player.id].setValue(player.family);
                
                // Optimize prayer token display for each player's current prayer count (redundant but safe)
                this.optimizePrayerTokens(player.id, player.prayer);
                
                // Initialize kept cards based on temple and amulet counts
                if (this[`${player.id}_kept`]) {
                    console.log(`Re-initializing kept cards for player ${player.id}: amulets=${player.amulet}, temples=${player.temple}`);
                    // Add amulet cards (kept_id = 1)
                    for (let i = 0; i < player.amulet; i++) {
                        this[`${player.id}_kept`].addToStock(1);
                    }
                    // Add temple cards (kept_id = 2)
                    for (let i = 0; i < player.temple; i++) {
                        this[`${player.id}_kept`].addToStock(2);
                    }
                    console.log('Re-initialized kept stock items:', this[`${player.id}_kept`].getAllItems());
                } else {
                    console.error('No kept stock found during re-initialization for player', player.id);
                }
            });

            // Set initial round leader prayer icon to grayed version
            if (gamedatas.round_leader) {
                this.updateRoundLeaderIcons(null, gamedatas.round_leader);
            }

            // Add prediction toggle button to the bottom right of resolved cards div
            // Only if 'Show End-Round Predictions' game option is enabled (option 101 == 2)
            const resolvedCardsDiv = document.getElementById('resolvedCards');
            if (resolvedCardsDiv && this.isPredictionsEnabled()) {
                resolvedCardsDiv.insertAdjacentHTML('beforeend', `
                    <div id="prediction_toggle_panel" style="
                        position: absolute; 
                        bottom: 5px; 
                        right: 5px; 
                        background: rgba(240,240,240,0.95); 
                        border-radius: 5px; 
                        padding: 5px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    ">
                        <button id="prediction_toggle_btn" style="
                            padding: 4px 8px; 
                            background: #4a90e2; 
                            color: white; 
                            border: none; 
                            border-radius: 3px; 
                            cursor: pointer; 
                            font-size: 11px;
                            white-space: nowrap;
                        ">
                            📊 Predictions
                        </button>
                    </div>
                `);
                
                // Add click event listener after creating the button
                const toggleBtn = document.getElementById('prediction_toggle_btn');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        this.togglePredictionPanel();
                    });
                }
            }
            
            // Add click event listener for prediction panel close button
            const panelHeader = document.getElementById('prediction_panel_header');
            if (panelHeader) {
                panelHeader.addEventListener('click', () => {
                    this.togglePredictionPanel();
                });
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
            
            // Initialize dev statistics display
            this.updateDevStatsDisplay();
            
            // Set up periodic refresh for dev stats (every 5 seconds)
            setInterval(() => {
                this.updateDevStatsDisplay();
            }, 5000);

        },

        ///////////////////////////////////////////////////
        //// Development Statistics Display
        
        updateDevStatsDisplay: function() {
            // Only update if the stats panel exists and is visible
            const statsPanel = document.getElementById('dev_stats_panel');
            if (!statsPanel) return;
            
            try {
                // Get current game data
                const players = this.gamedatas.players || {};
                
                // NOTE: This displays current game STATE, not accumulated STATISTICS
                // Real statistics would need to be fetched from server via AJAX call
                // For now, we'll show a mix of current game state and placeholders for real statistics
                
                // Calculate some basic statistics from current game state
                let totalCards = 0;
                let totalFamilies = 0;
                let totalTemples = 0;
                let totalAmulets = 0;
                let playersWithLeaders = 0;
                
                Object.values(players).forEach(player => {
                    totalCards += parseInt(player.cards) || 0;
                    totalFamilies += parseInt(player.family) || 0;
                    totalTemples += parseInt(player.temple) || 0;
                    totalAmulets += parseInt(player.amulet) || 0;
                    if (player.chief) playersWithLeaders++;
                });
                
                // Get atheist count from game state (if available)
                const atheistCount = this.atheists ? this.atheists.count() : '?';
                
                // Update table statistics 
                const tableStatsHtml = `
                    <div style="margin-bottom: 3px;">🌍 Total Players: <span style="color: #FFF;">${Object.keys(players).length}</span></div>
                    <div style="margin-bottom: 3px;">� Total Families: <span style="color: #FFF;">${totalFamilies}</span></div>
                    <div style="margin-bottom: 3px;">😈 Atheist Pool: <span style="color: #FFF;">${atheistCount}</span></div>
                    <div style="margin-bottom: 3px;">�️ Total Temples: <span style="color: #FFF;">${totalTemples}</span></div>
                    <div style="margin-bottom: 3px;">🧿 Total Amulets: <span style="color: #FFF;">${totalAmulets}</span></div>
                    <div style="margin-bottom: 3px;">👑 Players w/ Leaders: <span style="color: #FFF;">${playersWithLeaders}</span></div>
                    <div style="margin-bottom: 3px;">🃏 Total Cards in Hands: <span style="color: #FFF;">${totalCards}</span></div>
                `;
                document.getElementById('table_stats_content').innerHTML = tableStatsHtml;
                
                // Add refresh button for live statistics
                const tableStatsContainer = document.getElementById('table_stats_content');
                if (tableStatsContainer) {
                    const refreshButtonHtml = `
                        <div style="margin-top: 8px; margin-bottom: 5px;">
                            <button id="refresh_stats_btn" style="padding: 6px 18px; font-size: 18px; background: #4CAF50; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                🔄 Refresh Live Statistics
                            </button>
                        </div>
                        <div id="live_stats_content" style="margin-bottom: 8px; padding: 8px; background: rgba(0,0,0,0.2); border-radius: 3px;">
                            <div style="color: #FFE082; font-weight: bold; margin-bottom: 5px; font-size: 21px;">Live Statistics (click refresh):</div>
                            <div style="font-size: 20px; opacity: 0.7;">
                                <div style="font-size: 20px;">Total Rounds: <span style="color: #FFF;">?</span></div>
                                <div style="font-size: 20px;">Global Disasters: <span style="color: #FFF;">?</span></div>
                                <div style="font-size: 20px;">Local Disasters: <span style="color: #FFF;">?</span></div>
                                <div style="font-size: 20px;">Bonus Cards: <span style="color: #FFF;">?</span></div>
                                <div style="font-size: 20px;">Players Eliminated: <span style="color: #FFF;">?</span></div>
                            </div>
                        </div>
                    `;
                    tableStatsContainer.innerHTML = refreshButtonHtml + tableStatsHtml;
                    
                    // Add click handler for refresh button
                    const refreshBtn = document.getElementById('refresh_stats_btn');
                    if (refreshBtn) {
                        refreshBtn.onclick = () => this.refreshDevStatistics();
                    }
                }
                
                // Update player statistics
                let playerStatsHtml = '<div style="margin-bottom: 5px; color: #FFE082; font-weight: bold; font-size: 21px;">⚠️ Player Statistics (need server data)</div>';
                Object.values(players).forEach(player => {
                    const playerColor = this.fixPlayerColor(player.color);
                    const isEliminated = (player.family === 0 && !player.chief);
                    const statusIcon = isEliminated ? '💀' : (player.chief ? '👑' : '👤');
                    
                    playerStatsHtml += `
                        <div style="margin-bottom: 8px; padding: 5px; background: rgba(255,255,255,0.05); border-radius: 3px; ${isEliminated ? 'opacity: 0.6;' : ''}">
                            <div style="font-weight: bold; color: ${playerColor}; margin-bottom: 3px;">${statusIcon} ${player.name}</div>
                            <div style="font-size: 20px; line-height: 1.3; opacity: 0.7;">
                                Atheists Converted: <span style="color: #FFF;">?</span> | Believers Converted: <span style="color: #FFF;">?</span><br>
                                Families Lost: <span style="color: #FFF;">?</span> | Temples Built: <span style="color: #FFF;">?</span><br>
                                Amulets Gained: <span style="color: #FFF;">?</span> | Speeches Given: <span style="color: #FFF;">?</span><br>
                                Cards Played: <span style="color: #FFF;">?</span> | Disasters Doubled: <span style="color: #FFF;">?</span>
                            </div>
                            <div style="font-size: 20px; line-height: 1.3; margin-top: 3px; padding-top: 3px; border-top: 1px solid rgba(255,255,255,0.1);">
                                <strong>Current State:</strong><br>
                                Families: <span style="color: ${player.family > 0 ? '#4CAF50' : '#F44336'}">${player.family || 0}</span> | 
                                Prayer: <span style="color: ${player.prayer > 5 ? '#4CAF50' : player.prayer > 2 ? '#FF9800' : '#F44336'}">${player.prayer || 0}</span><br>
                                Happiness: <span style="color: ${player.happiness > 6 ? '#4CAF50' : player.happiness > 3 ? '#FF9800' : '#F44336'}">${player.happiness || 0}/10</span> | 
                                Temples: <span style="color: #2196F3">${player.temple || 0}</span> | 
                                Amulets: <span style="color: #9C27B0">${player.amulet || 0}</span><br>
                                Leader: <span style="color: ${player.chief ? '#4CAF50' : '#F44336'}">${player.chief ? 'Yes' : 'No'}</span> | 
                                Cards: <span style="color: ${player.cards > 3 ? '#4CAF50' : player.cards > 1 ? '#FF9800' : '#F44336'}">${player.cards || 0}</span>
                                ${isEliminated ? '<br><span style="color: #F44336; font-weight: bold;">❌ ELIMINATED</span>' : ''}
                            </div>
                        </div>
                    `;
                });
                document.getElementById('player_stats_content').innerHTML = playerStatsHtml;
                
            } catch (error) {
                console.log('Error updating dev stats display:', error);
                // Show error in stats panel
                document.getElementById('table_stats_content').innerHTML = '<div style="color: #F44336;">Error loading stats</div>';
                document.getElementById('player_stats_content').innerHTML = '<div style="color: #F44336;">Check console for details</div>';
            }
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
            
            // Create clean image tooltip without player-specific styling
            const imageUrl = g_gamethemeurl + 'img/Cards_1323_2000_compressed.png';
            
            const imgTooltip = `<img src="${imageUrl}" style="width: 262px; height: 400px; object-fit: none; object-position: ${bgPositionX}px ${bgPositionY}px; border: 2px solid #333; border-radius: 8px;" />`;
            
            // Add image tooltip
            this.addTooltipHtml(elementId, imgTooltip, 300);
        },
        
        addCardTooltipByUniqueId: function(stockName, uniqueId, playerId = null) {
            console.log(`DEBUG: addCardTooltipByUniqueId called with stockName=${stockName}, uniqueId=${uniqueId}, playerId=${playerId}`);
            
            const cardType = this.getCardTypeFromUniqueId(uniqueId);
            const cardId = this.getCardIdFromUniqueId(uniqueId);
            
            if (cardType && cardId !== null) {
                console.log(`Tooltip request: uniqueId=${uniqueId}, cardType=${cardType}, cardId=${cardId} for ${stockName}`);
                
                // Use BGA stock system's built-in tooltip functionality if available
                setTimeout(() => {
                    // Use provided playerId or try to find it from stored data
                    let finalPlayerId = playerId;
                    
                    if (this[stockName] && this[stockName].getAllItems) {
                        // Find the stock item with this uniqueId
                        const stockItems = this[stockName].getAllItems();
                        const targetItem = stockItems.find(item => item.type == uniqueId);
                        
                        if (targetItem) {
                            const elementId = this[stockName].getItemDivId(targetItem.id);
                            
                            // Try to get player ID from stored data if not provided
                            if (!finalPlayerId && this[stockName].items && this[stockName].items[targetItem.id] && this[stockName].items[targetItem.id].played_by) {
                                finalPlayerId = this[stockName].items[targetItem.id].played_by;
                                console.log(`DEBUG: Found stored playerId=${finalPlayerId} for card ${targetItem.id}`);
                            }
                            
                            if (elementId) {
                                this.addCardTooltip(elementId, cardType, cardId, finalPlayerId);
                                console.log(`Added tooltip to stock item ${targetItem.id} with element ID ${elementId}, playerId=${finalPlayerId}`);
                                return;
                            }
                        }
                    }
                    
                    // Fallback to previous method
                    this.addTooltipToLatestCard(stockName, uniqueId, cardType, cardId, finalPlayerId);
                }, 300);
            }
        },
        
        addTooltipToLatestCard: function(stockName, uniqueId, cardType, cardId, playerId = null) {
            console.log(`DEBUG: addTooltipToLatestCard called with playerId=${playerId}`);
            
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
            
            console.log(`Found ${allElements.length} elements in ${stockName}`);
            
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
                console.log(`Adding tooltip to latest element: ${elementId} -> uniqueId=${uniqueId}, playerId=${playerId}`);
                
                this.addCardTooltip(elementId, cardType, cardId, playerId);
                targetElement.setAttribute('data-tooltip-added', 'true');
                targetElement.setAttribute('data-unique-id', uniqueId);
                if (playerId) {
                    targetElement.setAttribute('data-played-by', playerId);
                }
            } else {
                console.log(`No available element found for tooltip in ${stockName}`);
            }
        },
        
        // Function to manually refresh tooltips for all visible cards
        refreshAllCardTooltips: function() {
            console.log("Refreshing all card tooltips...");
            
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
                    // Button setup is handled by onUpdateActionButtons
                    break;

                case 'phaseThreeRollDice':
                    if (this.isCurrentPlayerActive()) {
                        // Reset auto-roll tracking for this new state
                        this.autoRollAttempted = false;
                        
                        // Check if auto-roll dice preference is enabled
                        console.log('=== AUTO-ROLL DEBUG START (onEnteringState) ===');
                        console.log('Current state:', this.gamedatas.gamestate.name);
                        console.log('Current player active:', this.isCurrentPlayerActive());
                        console.log('Document body classes:', document.body.className);
                        
                        // Primary method: Check CSS class (BGA preference system)
                        let autoRollEnabled = document.body.classList.contains('kalua_auto_dice');
                        console.log('CSS class kalua_auto_dice found:', autoRollEnabled);
                        
                        if (!autoRollEnabled) {
                            // Fallback: Check preference objects (for debugging)
                            console.log('Checking preference objects as fallback');
                            console.log('this.prefs:', this.prefs);
                            console.log('this.player_preferences:', this.player_preferences);
                            
                            if (this.prefs && this.prefs[101]) {
                                console.log('Found prefs[101]:', this.prefs[101]);
                                if (this.prefs[101].value == 2) {
                                    autoRollEnabled = true;
                                    console.log('Auto-roll enabled via this.prefs fallback');
                                }
                            } else if (this.player_preferences && this.player_preferences[101]) {
                                console.log('Found player_preferences[101]:', this.player_preferences[101]);
                                if (this.player_preferences[101].value == 2) {
                                    autoRollEnabled = true;
                                    console.log('Auto-roll enabled via this.player_preferences fallback');
                                }
                            }
                        }
                        
                        console.log('Final auto-roll decision:', autoRollEnabled);
                        console.log('=== AUTO-ROLL DEBUG END (onEnteringState) ===');
                        
                        if (autoRollEnabled && !this.autoRollAttempted) {
                            // Auto-roll dice preference is enabled
                            console.log('Attempting auto-roll from onEnteringState');
                            this.autoRollAttempted = true;
                            
                            // Use immediate execution
                            try {
                                console.log('Executing auto-roll action immediately from onEnteringState');
                                this.bgaPerformAction('actRollDie', {});
                            } catch (error) {
                                console.error('Auto-roll action failed:', error);
                                // Reset flag so onUpdateActionButtons can try
                                this.autoRollAttempted = false;
                                // Fall back to manual mode
                                console.log('Falling back to manual dice rolling mode');
                                this.setupDiceRoll();
                            }
                        } else if (!autoRollEnabled) {
                            // Manual dice rolling
                            console.log('Manual dice rolling mode');
                            this.setupDiceRoll();
                        } else {
                            console.log('Auto-roll already attempted, skipping onEnteringState trigger');
                        }
                    }
                    break;

                case 'phaseThreeDiscard':
                    if (this.isCurrentPlayerActive()) {
                        this.setupDiscard();
                    }
                    break;

                case 'phaseThreePlayCard':
                    // Show predictions when players are making decisions about cards
                    // since the round might end and family redistribution will occur
                    // Only if 'Show End-Round Predictions' game option is enabled
                    if (this.isPredictionsEnabled()) {
                        this.predictionPanelEnabled = true;
                        this.showPredictionPanel();
                    }
                    break;

                case 'phaseFourConvertPray':
                    // Hide predictions during the actual family redistribution
                    this.hidePredictionPanel();
                    break;

                default:
                    // Perform actions for unknown state
                    break;
            }

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
                            
                            console.log(`Auto-passing for player ${this.player_id}: ${cardsInHand} cards, ${currentPrayer} prayer`);
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
                            
                            // For round leader, disable pass button until they've played a card
                            const roundLeaderPlayedCard = this.gamedatas.round_leader_played_card || 0;
                            if (roundLeaderPlayedCard === 0) {
                                // Use setTimeout to ensure the button is fully rendered before disabling
                                setTimeout(() => {
                                    const passBtn = document.getElementById('pass-btn');
                                    if (passBtn) {
                                        dojo.addClass(passBtn, 'disabled');
                                        console.log('Round leader pass button disabled - no cards played yet');
                                    } else {
                                        console.log('Pass button not found when trying to disable');
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
                        console.log("onUpdateActionButtons called for phaseThreeRollDice state");
                        
                        // Check if auto-roll is enabled using CSS class (primary method)
                        let isAutoRoll = document.body.classList.contains('kalua_auto_dice');
                        
                        if (!isAutoRoll) {
                            // Fallback: Check preference objects
                            console.log("CSS class not found, checking preference objects");
                            console.log("Preferences available:", this.prefs);
                            console.log("Auto-roll preference (101):", this.prefs && this.prefs[101] ? this.prefs[101] : 'not found');
                            
                            if (this.prefs && this.prefs[101] && this.prefs[101].value == 2) {
                                isAutoRoll = true;
                            } else if (this.player_preferences && this.player_preferences[101] && this.player_preferences[101].value == 2) {
                                isAutoRoll = true;
                            }
                        }
                        
                        console.log("Is auto-roll enabled:", isAutoRoll);
                        console.log("Auto-roll already attempted:", this.autoRollAttempted);
                        
                        // TRIGGER AUTO-ROLL HERE (when UI is fully ready) if not already attempted
                        if (isAutoRoll && this.isCurrentPlayerActive() && !this.autoRollAttempted) {
                            console.log("Triggering auto-roll from onUpdateActionButtons");
                            this.autoRollAttempted = true;
                            
                            // Use a small delay to ensure the action buttons are fully setup
                            setTimeout(() => {
                                if (this.gamedatas.gamestate.name === 'phaseThreeRollDice' && this.isCurrentPlayerActive()) {
                                    console.log('Executing auto-roll action from onUpdateActionButtons');
                                    try {
                                        this.bgaPerformAction('actRollDie', {});
                                    } catch (error) {
                                        console.error('Auto-roll action failed in onUpdateActionButtons:', error);
                                    }
                                } else {
                                    console.log('Auto-roll cancelled - state changed before execution');
                                }
                            }, 50); // Very short delay just to ensure buttons are ready
                        } else if (isAutoRoll && this.autoRollAttempted) {
                            console.log("Auto-roll enabled but already attempted, skipping onUpdateActionButtons trigger");
                        } else if (!isAutoRoll && this.isCurrentPlayerActive() && !this.autoRollAttempted) {
                            // Set up a backup checker for auto-roll in case preferences load later
                            console.log("Auto-roll not detected yet, setting up backup checker");
                            let checkCount = 0;
                            const maxChecks = 10;
                            const checkInterval = setInterval(() => {
                                checkCount++;
                                const hasAutoClass = document.body.classList.contains('kalua_auto_dice');
                                console.log(`Auto-roll backup check ${checkCount}/${maxChecks}: CSS class found = ${hasAutoClass}`);
                                
                                if (hasAutoClass && !this.autoRollAttempted && this.gamedatas.gamestate.name === 'phaseThreeRollDice' && this.isCurrentPlayerActive()) {
                                    console.log('Auto-roll CSS class detected by backup checker - triggering auto-roll');
                                    this.autoRollAttempted = true;
                                    clearInterval(checkInterval);
                                    try {
                                        this.bgaPerformAction('actRollDie', {});
                                    } catch (error) {
                                        console.error('Auto-roll action failed in backup checker:', error);
                                    }
                                } else if (checkCount >= maxChecks || this.gamedatas.gamestate.name !== 'phaseThreeRollDice' || !this.isCurrentPlayerActive()) {
                                    console.log('Auto-roll backup checker stopping');
                                    clearInterval(checkInterval);
                                }
                            }, 200); // Check every 200ms
                        }
                        
                        const buttonText = isAutoRoll ? _('Roll Dice (Auto)') : _('Roll Dice');
                        
                        this.addActionButton('roll-dice-btn', buttonText, () => {
                            // Only allow manual rolling if auto-roll is disabled
                            if (!isAutoRoll) {
                                console.log("Manual dice roll button clicked");
                                // Disable the button immediately to prevent double-clicks
                                const rollBtn = document.getElementById('roll-dice-btn');
                                if (rollBtn) {
                                    rollBtn.disabled = true;
                                    rollBtn.style.opacity = '0.5';
                                }
                                this.bgaPerformAction('actRollDie', {});
                            } else {
                                console.log("Auto-roll is enabled - manual click ignored");
                            }
                        });
                        
                        // If auto-roll is enabled, disable the button and style it as inactive
                        if (isAutoRoll) {
                            console.log("Styling button as auto-roll mode");
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
                        console.log("onUpdateActionButtons called for phaseThreeResolveAmulets state");
                        
                        // Note: Do not call updatePageTitle() here as it causes infinite recursion
                        // The page title will be updated by the BGA framework automatically
                        
                        // Check if current player has amulets
                        const currentPlayerId = this.player_id;
                        const currentPlayerAmulets = this.gamedatas.players[currentPlayerId]?.amulet || 0;
                        
                        if (currentPlayerAmulets > 0) {
                            this.addActionButton('use-amulet-btn', _('Use Amulet'), () => {
                                console.log("Player clicked Use Amulet");
                                this.disableAmuletButtons();
                                this.bgaPerformAction('actAmuletChoose', { use_amulet: true });
                            });
                            
                            this.addActionButton('no-amulet-btn', _('Do Not Use Amulet'), () => {
                                console.log("Player clicked Do Not Use Amulet");
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
        //// Prayer token management helper function
        
        updatePlayerPrayer: function(playerId, newPrayerValue) {
            if (this.prayerCounters[playerId]) {
                this.prayerCounters[playerId].setValue(newPrayerValue);
                this.optimizePrayerTokens(playerId, newPrayerValue);
                console.log(`Updated player ${playerId} prayer to ${newPrayerValue}`);
            } else {
                console.warn(`No prayer counter found for player ${playerId}`);
            }
        },


        ///////////////////////////////////////////////////
        //// Prayer token optimization helper
        
        optimizePrayerTokens: function(playerId, targetCount) {
            const playerZone = this[`${playerId}_prayer_zone`];
            if (!playerZone) {
                console.warn(`No prayer token zone found for player ${playerId}`);
                return;
            }
            
            // Clear all current tokens
            playerZone.removeAll();
            
            // Create prayer token elements and add to zone for organic arrangement
            if (targetCount < 6) {
                // Add individual single tokens
                for (let i = 0; i < targetCount; i++) {
                    const tokenId = `${playerId}_prayer_${i}`;
                    this.createPrayerTokenElement(tokenId, 1, playerId); // Type 1 = single prayer token
                    playerZone.placeInZone(tokenId);
                }
                console.log(`Prayer tokens for player ${playerId}: ${targetCount} individual tokens (less than 6)`);
            } else {
                // For 6+ tokens: always show 1-5 individual tokens plus grouped tokens for visual significance
                const totalForGrouping = targetCount - 1;
                const fiveTokens = Math.floor(totalForGrouping / 5);
                const remainingTokens = targetCount - (fiveTokens * 5);
                const singleTokens = Math.max(1, Math.min(5, remainingTokens));
                
                let tokenIndex = 0;
                
                // Add five-token representations with slight randomization
                for (let i = 0; i < fiveTokens; i++) {
                    const tokenId = `${playerId}_prayer_5group_${i}`;
                    this.createPrayerTokenElement(tokenId, 2, playerId); // Type 2 = five prayer tokens
                    playerZone.placeInZone(tokenId);
                    this.addOrganicPositioning(tokenId, tokenIndex++);
                }
                
                // Add individual tokens with organic positioning
                for (let i = 0; i < singleTokens; i++) {
                    const tokenId = `${playerId}_prayer_single_${i}`;
                    this.createPrayerTokenElement(tokenId, 1, playerId); // Type 1 = single prayer token
                    playerZone.placeInZone(tokenId);
                    this.addOrganicPositioning(tokenId, tokenIndex++);
                }
                
                console.log(`Optimized prayer tokens for player ${playerId}: ${fiveTokens} five-tokens + ${singleTokens} individual-tokens = ${targetCount} total (organic arrangement)`);
            }
        },
        
        ///////////////////////////////////////////////////
        //// Create prayer token DOM elements
        
        createPrayerTokenElement: function(elementId, tokenType, playerId) {
            // Remove existing element if it exists
            const existingElement = document.getElementById(elementId);
            if (existingElement) {
                existingElement.remove();
            }
            
            // Create new prayer token element
            const tokenElement = document.createElement('div');
            tokenElement.id = elementId;
            tokenElement.className = 'prayer_token';
            tokenElement.style.width = '30px';
            tokenElement.style.height = '30px';
            tokenElement.style.backgroundImage = `url('${g_gamethemeurl}img/30_30_prayertokens.png')`;
            tokenElement.style.backgroundSize = '60px 30px'; // 2 images per row
            tokenElement.style.backgroundRepeat = 'no-repeat';
            tokenElement.style.backgroundPosition = tokenType === 1 ? '0px 0px' : '-30px 0px';
            tokenElement.style.opacity = '0.5'; // Apply the opacity directly
            tokenElement.style.position = 'absolute';
            tokenElement.style.cursor = 'default';
            
            // Add to prayer content div
            const prayerContent = document.getElementById(`${playerId}_PrayerContent`);
            if (prayerContent) {
                prayerContent.appendChild(tokenElement);
            }
        },
        
        ///////////////////////////////////////////////////
        //// Add organic positioning to prayer tokens
        
        addOrganicPositioning: function(elementId, index) {
            setTimeout(() => {
                const element = document.getElementById(elementId);
                if (element) {
                    // Add slight random offset for organic pile effect
                    const randomX = (Math.random() - 0.5) * 10; // -5 to +5 pixels
                    const randomY = (Math.random() - 0.5) * 10; // -5 to +5 pixels
                    const randomRotation = (Math.random() - 0.5) * 20; // -10 to +10 degrees
                    
                    // Apply transform for organic look
                    element.style.transform = `translate(${randomX}px, ${randomY}px) rotate(${randomRotation}deg)`;
                    element.style.zIndex = 100 + index; // Layer tokens naturally
                }
            }, 50 * index); // Stagger the positioning for animation effect
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
            
            console.log(`Updating global disaster card ${cardId} (type_arg ${cardTypeArg}) to ${multiplierChoice} - image position ${newImagePos}`);
            
            // Update the card image in the played cards stock
            if (this['played'] && this['played'].item_type && this['played'].item_type[uniqueId]) {
                this['played'].item_type[uniqueId].image_position = newImagePos;
                
                // Force a visual update of the specific card if it exists in the played stock
                const playedItems = this['played'].getAllItems();
                const targetItem = playedItems.find(item => item.id == cardId);
                if (targetItem) {
                    // Remove and re-add the item to force visual update
                    this['played'].removeFromStockById(cardId);
                    this['played'].addToStockWithId(uniqueId, cardId);
                    console.log(`Visually updated played card ${cardId} with new image`);
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
            console.log("INVALID CARD TYPE!!"); /* TODO exception? */
            return 0;
        },

        playCardOnTable : function(player_id, color, value, card_id) {
            console.log(`DEBUG: playCardOnTable called - player_id=${player_id}, card_id=${card_id}`);
            console.log(`DEBUG: Available players in gamedatas:`, this.gamedatas ? Object.keys(this.gamedatas.players || {}) : 'no gamedatas');
            
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                    this[`${player_id}_cards`].removeFromStockById(card_id);
                    if (this.cardCounters[player_id]) {
                        const currentValue = this.cardCounters[player_id].getValue();
                        this.cardCounters[player_id].setValue(Math.max(0, currentValue - 1));
                    }

            // Add card to played cards area
            const uniqueId = this.getCardUniqueId(parseInt(color), parseInt(value)); // Generate unique ID
            console.log("playing unique ID " + uniqueId)
            this['played'].addToStockWithId(uniqueId, card_id); // Add card to played cards area  
            
            // Store player info for this card for future reference
            if (this['played'] && this['played'].items && this['played'].items[card_id]) {
                this['played'].items[card_id].played_by = player_id;
                console.log(`DEBUG: Stored played_by=${player_id} on card ${card_id}`);
            }

            // Add tooltip with player information
            setTimeout(() => {
                console.log(`DEBUG: Setting up tooltip for card ${card_id} played by player ${player_id}`);
                
                const cardType = this.getCardTypeFromUniqueId(uniqueId);
                const cardIdFromUnique = this.getCardIdFromUniqueId(uniqueId);
                
                console.log(`DEBUG: Card details - uniqueId=${uniqueId}, cardType=${cardType}, cardIdFromUnique=${cardIdFromUnique}`);
                
                if (cardType && cardIdFromUnique !== null) {
                    // Find the DOM element for this card
                    const stockItems = this['played'].getAllItems();
                    console.log(`DEBUG: Found ${Object.keys(stockItems).length} items in played stock`);
                    
                    const targetItem = Object.values(stockItems).find(item => item.type == uniqueId);
                    console.log(`DEBUG: Target item found:`, targetItem);
                    
                    if (targetItem) {
                        const elementId = this['played'].getItemDivId(targetItem.id);
                        console.log(`DEBUG: Element ID from stock: ${elementId}`);
                        
                        if (elementId) {
                            // Add tooltip with player information
                            this.addCardTooltip(elementId, cardType, cardIdFromUnique, player_id);
                            console.log(`DEBUG: Called addCardTooltip with player_id=${player_id}`);
                        } else {
                            console.warn(`DEBUG: Could not get element ID for target item`);
                        }
                    } else {
                        console.warn(`DEBUG: Could not find target item with uniqueId ${uniqueId}`);
                        // Try alternative approach - find by card_id directly
                        const fallbackElementId = `played_item_${card_id}`;
                        console.log(`DEBUG: Trying fallback element ID: ${fallbackElementId}`);
                        this.addCardTooltip(fallbackElementId, cardType, cardIdFromUnique, player_id);
                    }
                } else {
                    console.warn(`DEBUG: Invalid card type or ID - cardType=${cardType}, cardIdFromUnique=${cardIdFromUnique}`);
                }
                
                // Add visual player indicator
                this.addPlayerIndicator(card_id, player_id, 'played');
            }, 200);

            console.log(`Card ${card_id} played by player ${player_id}`);
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
                        console.log(`Fixed player indicator color from ${originalColor} to ${playerColor}`);
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
                        console.log(`Added player indicator for ${playerName} (${playerColor}) to card ${card_id}`);
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
            console.log(`DEBUG: Adding border for card ${card_id}, player ${player_id}, stock ${stock_name}`);
            
            if (!player_id || !this.gamedatas.players[player_id]) {
                console.warn(`No player data found for player ${player_id}`);
                return;
            }
            
            // Get player's fixed color using helper function
            let fixedColor = this.gamedatas.players[player_id].color;
            console.log(`Player ${player_id} color from gamedatas: ${fixedColor}`);
            
            const originalColor = fixedColor;
            fixedColor = this.fixPlayerColor(fixedColor);
            if (fixedColor !== originalColor) {
                console.log(`Fixed card border color from ${originalColor} to ${fixedColor}`);
            } else {
                console.log(`Card border color already complete: ${fixedColor}`);
            }
            
            // Apply CSS class using BGA Stock extraClasses functionality
            const colorClass = this.getPlayerColorClass(fixedColor);
            console.log(`Color class for player ${player_id}: ${colorClass}`);
            
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
                                console.log(`Applied ${colorClass} via addExtraClass to card ${card_id} (attempt ${attempt})`);
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
                                    console.log(`Applied ${colorClass} via selector "${selector}" to card ${card_id} (attempt ${attempt})`);
                                    console.log(`Card element classes now: ${cardElement.className}`);
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
                                    console.log(`Applied ${colorClass} via stockitem class to card ${card_id} (attempt ${attempt})`);
                                });
                                applied = true;
                            }
                        }
                        
                        if (!applied && attempt < maxAttempts) {
                            console.log(`Border application failed for card ${card_id}, retrying (attempt ${attempt + 1}/${maxAttempts})`);
                            attemptBorderApplication(attempt + 1, maxAttempts);
                        } else if (!applied) {
                            console.error(`Could not find or apply color class to card ${card_id} in stock ${stock_name} after ${maxAttempts} attempts`);
                            // Log all possible elements for debugging
                            const allElements = document.querySelectorAll(`[id*="${card_id}"]`);
                            console.log(`Found ${allElements.length} elements with card_id ${card_id}:`, allElements);
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
                '#4685FF': 'player-card-blue',
                '#C22D2D': 'player-card-red',
                '#C8CA25': 'player-card-yellow',
                '#2EA232': 'player-card-green',
                '#913CB3': 'player-card-purple'
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
                    console.log(`Fixed truncated color: ${color} -> ${fixedColor}`);
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
            console.log("Drawing a card");

            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg)); // Generate unique ID
            console.log("drawing unique ID " + uniqueId)

            this[`${player}_cards`].addToStockWithId(uniqueId, card_id); // Add card to player's hand
            this.addCardTooltipByUniqueId(`${player}_cards`, uniqueId); // Add tooltip
            console.log(`Card ${card_id} added to player ${player}'s hand`);            
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
            const currentValue = this.happinessCounters[player_id].getValue();
            const newValue = Math.max(0, Math.min(10, currentValue + 1));
            this.happinessCounters[player_id].setValue(newValue); // Increase happiness by 1, clamped to 0-10
            // Use the player's sprite value from gamedatas to move the correct token
            const sprite = this.gamedatas.players[player_id].sprite;
            this.movetokens(sprite-1, 1);
            
            // Update prediction panel if active
            this.refreshPredictionPanelIfActive();
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

        // Calculate predicted family and happiness changes during convert/pray phase
        calculatePrayConvertPredictions: function() {
            const predictions = {};
            const allPlayers = Object.values(this.gamedatas.players);
            const happinessScores = {};
            
            // Collect current happiness scores (including temple bonuses)
            allPlayers.forEach(player => {
                const currentHappiness = this.happinessCounters[player.id].getValue();
                const templeCount = this.templeCounters[player.id].getValue();
                // Happiness with temple bonus (clamped 0-10)
                happinessScores[player.id] = Math.max(0, Math.min(10, currentHappiness + templeCount));
            });

            // Find lowest and highest happiness scores
            const happinessValues = Object.values(happinessScores);
            const happy_value_low = Math.min(...happinessValues);
            const happy_value_high = Math.max(...happinessValues);

            // Get high happiness players
            const high_happiness_players = allPlayers.filter(player => 
                happinessScores[player.id] === happy_value_high
            );
            const high_players = high_happiness_players.length;

            // Calculate predictions for each player
            allPlayers.forEach(player => {
                const player_id = player.id;
                const currentFamilies = this.familyCounters[player_id].getValue();
                const currentPrayer = this.prayerCounters[player_id].getValue();
                const currentHappiness = happinessScores[player_id]; // Already includes temple bonus
                const templeCount = this.templeCounters[player_id].getValue();
                
                let predictedFamilies = currentFamilies;
                let predictedPrayer = currentPrayer;
                let predictedHappiness = currentHappiness; // Already includes temple bonus

                // Family redistribution (only if not everyone has same happiness)
                if (happy_value_low !== happy_value_high) {
                    if (happinessScores[player_id] === happy_value_low) {
                        // Lose 2 families (or all if less than 2)
                        const to_lose = Math.min(2, currentFamilies);
                        predictedFamilies -= to_lose;
                    } else if (happinessScores[player_id] !== happy_value_high) {
                        // Lose 1 family (middle happiness)
                        if (currentFamilies > 0) {
                            predictedFamilies -= 1;
                        }
                    } else {
                        // High happiness: receive families from the pool
                        // Calculate total converted pool
                        let converted_pool = 0;
                        allPlayers.forEach(p => {
                            const p_families = this.familyCounters[p.id].getValue();
                            if (happinessScores[p.id] === happy_value_low) {
                                converted_pool += Math.min(2, p_families);
                            } else if (happinessScores[p.id] !== happy_value_high && p_families > 0) {
                                converted_pool += 1;
                            }
                        });
                        
                        // Divide families among high happiness players
                        const fams_to_happy = Math.floor(converted_pool / high_players);
                        predictedFamilies += fams_to_happy;
                    }
                }

                // Prayer calculation: 1 per 5 families + bonuses + temples
                predictedPrayer += Math.floor(predictedFamilies / 5);
                if (happinessScores[player_id] === happy_value_low) {
                    predictedPrayer += 4;
                } else if (happinessScores[player_id] !== happy_value_high) {
                    predictedPrayer += 2;
                }
                predictedPrayer += templeCount; // Temple prayer bonus

                predictions[player_id] = {
                    familyChange: predictedFamilies - currentFamilies,
                    prayerChange: predictedPrayer - currentPrayer,
                    happinessChange: predictedHappiness - this.happinessCounters[player_id].getValue(), // Temple bonus effect
                    predictedFamilies: predictedFamilies,
                    predictedPrayer: predictedPrayer,
                    predictedHappiness: predictedHappiness
                };
            });

            return predictions;
        },

        // Update and show the prediction panel
        updatePredictionPanel: function() {
            // Check if 'Show End-Round Predictions' game option is enabled
            if (!this.isPredictionsEnabled() || !this.predictionPanelEnabled) return;
            
            const predictions = this.calculatePrayConvertPredictions();
            const content = document.getElementById('prediction_content');
            const panel = document.getElementById('prediction_panel');
            
            if (!content || !panel) return;

            let html = '';
            Object.values(this.gamedatas.players).forEach(player => {
                const prediction = predictions[player.id];
                const playerName = player.name;
                const isMe = player.id == this.player_id;
                
                html += `<div style="margin-bottom: 3px; ${isMe ? 'font-weight: bold; color: #ffff80;' : ''}">`;
                html += `<span style="color: ${this.fixPlayerColor(player.color)};">●</span> ${playerName}:<br>`;
                
                // Family change
                if (prediction.familyChange !== 0) {
                    const familyColor = prediction.familyChange > 0 ? '#80ff80' : '#ff8080';
                    html += `&nbsp;&nbsp;👨‍👩‍👧‍👦 ${prediction.familyChange > 0 ? '+' : ''}${prediction.familyChange}<br>`;
                }
                
                // Prayer change
                if (prediction.prayerChange !== 0) {
                    const prayerColor = prediction.prayerChange > 0 ? '#80ff80' : '#ff8080';
                    html += `&nbsp;&nbsp;🙏 ${prediction.prayerChange > 0 ? '+' : ''}${prediction.prayerChange}<br>`;
                }
                
                // Happiness change
                if (prediction.happinessChange !== 0) {
                    const happinessColor = prediction.happinessChange > 0 ? '#80ff80' : '#ff8080';
                    html += `&nbsp;&nbsp;😊 ${prediction.happinessChange > 0 ? '+' : ''}${prediction.happinessChange}<br>`;
                }
                
                html += `</div>`;
            });
            
            content.innerHTML = html;
            panel.style.display = 'block';
        },

        // Helper function to check if end-round predictions are enabled
        isPredictionsEnabled: function() {
            // Check game options (standard BGA pattern)
            if (this.gamedatas.game_options && this.gamedatas.game_options[101]) {
                return this.gamedatas.game_options[101] == 2;
            }
            
            // Fallback: check if options are stored differently
            if (this.gamedatas.options && this.gamedatas.options[101]) {
                return this.gamedatas.options[101] == 2;
            }
            
            // Another fallback: check gameinfos (some BGA games store it here)
            if (this.gamedatas.gameinfos && this.gamedatas.gameinfos.game_options && this.gamedatas.gameinfos.game_options[101]) {
                return this.gamedatas.gameinfos.game_options[101] == 2;
            }
            
            console.log('Warning: Could not find Show End-Round Predictions game option, defaulting to disabled');
            console.log('Available gamedatas keys:', Object.keys(this.gamedatas));
            
            // Default to disabled if option not found
            return false;
        },

        // Show prediction panel if appropriate game state
        showPredictionPanel: function() {
            // Check if 'Show End-Round Predictions' game option is enabled
            if (!this.isPredictionsEnabled() || !this.predictionPanelEnabled) return;
            
            const panel = document.getElementById('prediction_panel');
            if (panel) {
                this.updatePredictionPanel();
                panel.style.display = 'block';
            }
        },

        // Hide prediction panel
        hidePredictionPanel: function() {
            const panel = document.getElementById('prediction_panel');
            if (panel) {
                panel.style.display = 'none';
            }
        },

        // Toggle prediction panel visibility
        togglePredictionPanel: function() {
            // Check if 'Show End-Round Predictions' game option is enabled
            if (!this.isPredictionsEnabled()) return;
            
            this.predictionPanelEnabled = !this.predictionPanelEnabled;
            const toggleButton = document.getElementById('prediction_toggle_btn');
            
            if (this.predictionPanelEnabled) {
                this.showPredictionPanel();
                if (toggleButton) {
                    toggleButton.style.background = '#28a745';
                    toggleButton.innerHTML = '📊 Hide';
                }
            } else {
                this.hidePredictionPanel();
                if (toggleButton) {
                    toggleButton.style.background = '#4a90e2';
                    toggleButton.innerHTML = '📊 Predictions';
                }
            }
        },

        setupAmuletDecision: function() {
            console.log("Setting up amulet decision for player");
            /* Present amulet usage choice to the player */
            this.gamedatas.gamestate.descriptionmyturn = _('Do you want to use an amulet to avoid the disaster effects?');
            this.updatePageTitle();
            this.statusBar.removeActionButtons();
            
            this.addActionButton('use-amulet-btn', _('Use Amulet'), () => {
                console.log("Player clicked Use Amulet");
                this.disableAmuletButtons();
                this.bgaPerformAction('actAmuletChoose', { use_amulet: true });
            });
            
            this.addActionButton('no-amulet-btn', _('Do Not Use Amulet'), () => {
                console.log("Player clicked Do Not Use Amulet");
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
            console.log("Setting up dice roll for player");
            /* Present dice rolling option to the player */
            // Note: Buttons are now handled in onUpdateActionButtons method
            // This function can be used for other setup if needed in the future
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
            
            // Add 0.5 second delays to card resolution notifications so players can follow along
            this.notifqueue.setSynchronous('playerCountsChanged', 500);
            this.notifqueue.setSynchronous('familiesConverted', 500);
            this.notifqueue.setSynchronous('familiesDied', 500);
            this.notifqueue.setSynchronous('templeDestroyed', 500);
            this.notifqueue.setSynchronous('leaderRecovered', 500);
            this.notifqueue.setSynchronous('templeBuilt', 500);
            this.notifqueue.setSynchronous('amuletGained', 500);
            this.notifqueue.setSynchronous('cardResolved', 500);
            this.notifqueue.setSynchronous('cardBeingResolved', 500);
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
            
            /* Update counter with protection against negative values */
            if (this.cardCounters[player_id]) {
                const currentValue = this.cardCounters[player_id].getValue();
                this.cardCounters[player_id].setValue(Math.max(0, currentValue + 1));
            }
        },

        notif_quickstartCardsDealt: async function( args )
        {
            console.log('Quickstart cards dealt to all players');
            
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
            }
            
            // Remove the cardback from other players' view
            const cardbackStock = this[`${args.player_id}_cardbacks`];
            if (cardbackStock) {
                cardbackStock.removeFromStockById(args.card_id);
            }
            
            // Update card counter with exact count from server (prevents negative values)
            if (this.cardCounters[args.player_id] && args.card_count !== undefined) {
                this.cardCounters[args.player_id].setValue(Math.max(0, args.card_count));
            }
            
            // Add the card to the played stock
            const uniqueId = this.getCardUniqueId(parseInt(args.card_type), parseInt(args.card_type_arg));
            if (this['played']) {
                this['played'].addToStockWithId(uniqueId, args.card_id);
                
                // Store player info and add tooltip with player information
                if (this['played'].items && this['played'].items[args.card_id]) {
                    this['played'].items[args.card_id].played_by = args.player_id;
                }
                this.addCardTooltipByUniqueId('played', uniqueId, args.player_id);
                
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
                    this.addCardTooltipByUniqueId(`${this.player_id}_cards`, uniqueId);
                }
            }
        },

        notif_prayerSpent: function(args) {
            // Update prayer counter and tokens for the player who spent prayer points
            this.updatePlayerPrayer(args.player_id, args.new_prayer_total);
            
            console.log(`Player ${args.player_id} spent ${args.prayer_spent} prayer points, new total: ${args.new_prayer_total}`);
        },

        notif_globalDisasterChoice: function(args) {
            // Update the global disaster card image based on the multiplier choice
            console.log('Global disaster choice made:', args);
            
            if (args.card_id && args.card_type_arg && args.choice) {
                this.updateGlobalDisasterCardImage(args.card_id, args.card_type_arg, args.choice);
            }
            
            // Log the choice for reference
            console.log(`Global disaster ${args.card_id} choice: ${args.choice} by player ${args.player_id}`);
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
            
            console.log('Round leader has now played a card, pass button enabled, convert button disabled');
        },

        notif_roundLeaderTurnStart: function(args) {
            // Update the game state data for round leader
            this.gamedatas.round_leader_played_card = args.round_leader_played_card || 0;
            
            console.log('Round leader turn started, round_leader_played_card =', this.gamedatas.round_leader_played_card);
            
            // If round leader hasn't played a card yet and this player is the round leader
            if (this.gamedatas.round_leader_played_card === 0 && this.player_id == this.gamedatas.round_leader) {
                // Disable pass button
                setTimeout(() => {
                    const passBtn = document.getElementById('pass-btn');
                    if (passBtn && !dojo.hasClass(passBtn, 'disabled')) {
                        dojo.addClass(passBtn, 'disabled');
                        console.log('Round leader pass button disabled at turn start - no cards played yet');
                    }
                }, 50);
                
                // Re-enable convert button if it exists and is currently disabled
                setTimeout(() => {
                    const convertBtn = document.getElementById('convert-btn');
                    if (convertBtn && dojo.hasClass(convertBtn, 'disabled')) {
                        dojo.removeClass(convertBtn, 'disabled');
                        console.log('Round leader convert button re-enabled at turn start - new round of card playing');
                    }
                }, 50);
            }
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
            console.log('Amulet phase skipped - no players have amulets');
            // Just a notification that the amulet phase was skipped
        },

        notif_amuletUsed: function(args) {
            console.log('Player used amulet:', args);
            const player_name = args.player_name;
            const player_id = args.player_id;
            
            // Update amulet counter
            if (this.amuletCounters[player_id]) {
                // The counter should already be decremented by the server
                // Visual feedback could be added here to show amulet usage
            }
        },

        notif_amuletNotUsed: function(args) {
            console.log('Player chose not to use amulet:', args);
            const player_name = args.player_name;
            // Visual feedback that player declined to use amulet
            // Visual feedback could be added here
        },

        notif_amuletProtection: function(args) {
            console.log('Player protected by amulet:', args);
            const player_name = args.player_name;
            const player_id = args.player_id;
            
            // Visual feedback could be added here to show amulet protection
            // For example, a brief animation or highlighting of the player's board
        },

        notif_diceRollRequired: function(args) {
            console.log('Dice roll required:', args);
            // Players who need to roll dice will be prompted in the setupDiceRoll method
        },

        notif_diceRolled: function(args) {
            console.log('Player rolled dice:', args);
            const player_name = args.player_name;
            const player_id = args.player_id;
            const result = args.result;
            
            // Reset auto-roll flag when any player rolls dice
            // This allows auto-roll to work again if we return to dice rolling state
            if (player_id == this.player_id) {
                console.log('Resetting auto-roll flag after own dice roll');
                this.autoRollAttempted = false;
            }
            
            // Update only the specific player's die
            if (player_id && this.gamedatas.players[player_id]) {
                const player = this.gamedatas.players[player_id];
                // Calculate which dice face to show: player's color row + rolled result
                const playerDieFace = ((player.sprite - 1) * 6) + result;
                
                // Remove the old die for this player and add the new result
                this['dice'].removeFromStockById(player_id);
                this['dice'].addToStockWithId(playerDieFace, player_id);
            }
        },

        notif_templeIncremented: function(args) {
            console.log('Temple incremented for player:', args);
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
            console.log('Kept stock for player', player_id, ':', keptStock);
            if (keptStock) {
                console.log('Attempting to add temple (kept_id=2) to stock...');
                keptStock.addToStock(2);
                console.log('Added temple to kept stock for player', player_id);
                console.log('Stock items after adding:', keptStock.getAllItems());
            } else {
                console.error('No kept stock found for player', player_id);
            }
        },

        notif_amuletIncremented: function(args) {
            console.log('Amulet incremented for player:', args);
            const player_id = args.player_id;
            // Update amulet counter
            if (this.amuletCounters[player_id]) {
                this.amuletCounters[player_id].incValue(1);
            }
            
            // Add amulet card to kept area (kept_id = 1 for amulet, based on comment in setup)
            const keptStock = this[`${player_id}_kept`];
            console.log('Kept stock for player', player_id, ':', keptStock);
            if (keptStock) {
                console.log('Attempting to add amulet (kept_id=1) to stock...');
                keptStock.addToStock(1);
                console.log('Added amulet to kept stock for player', player_id);
                console.log('Stock items after adding:', keptStock.getAllItems());
            } else {
                console.error('No kept stock found for player', player_id);
            }
        },

        notif_templeDestroyed: function(args) {
            console.log('Temple destroyed for player:', args);
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
                console.log('Removed', temples_destroyed, 'temple(s) from kept stock for player', player_id);
            }
        },

        notif_templeBonus: function(args) {
            console.log('Temple bonus notification:', args);
            // Just a notification message - the prayer counter will be updated by playerCountsChanged
            // Update prayer token display if prayer value is provided
            if (args.prayer !== undefined && args.player_id) {
                this.updatePlayerPrayer(args.player_id, args.prayer);
            }
        },

        notif_playerCountsChanged: function(args) {
            console.log('Player counts changed:', args);
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
            
            // Update prediction panel if active
            this.refreshPredictionPanelIfActive();
            
            // Update dev statistics display
            this.updateDevStatsDisplay();
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
            
            // Update card counter (prevent negative values)
            if (this.cardCounters[player_id]) {
                const currentValue = this.cardCounters[player_id].getValue();
                this.cardCounters[player_id].setValue(Math.max(0, currentValue - 1));
            }
            
            console.log(`Card ${card_id} discarded by player ${player_id}`);
        },

        notif_cardResolved: function(args) {
            console.log('Card resolved:', args);
            const card_id = args.card_id;
            const card_type = args.card_type;
            const card_type_arg = args.card_type_arg;
            
            // Move card from played stock to resolved stock
            const uniqueId = this.getCardUniqueId(parseInt(card_type), parseInt(card_type_arg));
            
            // Remove from played stock
            if (this['played']) {
                this['played'].removeFromStockById(card_id);
            }
            
            // Add to resolved stock
            if (this['resolved']) {
                this['resolved'].addToStockWithId(uniqueId, card_id);
                this.addCardTooltipByUniqueId('resolved', uniqueId);
            }
            
            console.log(`Card ${card_id} (${args.card_name}) moved to resolved`);
        },

        notif_resolvedCardsCleanup: function(args) {
            console.log('Cleaning up resolved cards:', args);
            
            // Clear all cards from the resolved stock
            if (this['resolved']) {
                this['resolved'].removeAll();
            }
            
            console.log(`Cleaned up ${args.resolved_cards_count} resolved cards from UI`);
        },

        notif_allCardsCleanup: function(args) {
            console.log('Cleaning up all played and resolved cards for new round:', args);
            
            // Clear all cards from both played and resolved stocks
            if (this['played']) {
                this['played'].removeAll();
                console.log(`Cleared ${args.played_cards_count} cards from played stock`);
            }
            
            if (this['resolved']) {
                this['resolved'].removeAll();
                console.log(`Cleared ${args.resolved_cards_count} cards from resolved stock`);
            }
            
            console.log(`Total ${args.total_cards_count} cards moved to discard for new round`);
        },

        notif_familiesConverted: function(args) {
            console.log('Families converted to atheism:', args);
            
            // Update family counter for the affected player
            if (this.familyCounters[args.player_id]) {
                this.familyCounters[args.player_id].setValue(args.families_remaining);
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
            console.log('Families died:', args);
            
            // Update family counter for the affected player
            if (this.familyCounters[args.player_id]) {
                this.familyCounters[args.player_id].setValue(args.families_remaining);
            }
        },

        notif_devStatisticsRefreshed: function(args) {
            console.log('Development statistics refreshed:', args);
            
            // Update the statistics panel with the new data
            if (args.statistics) {
                this.updateDevStatisticsWithServerData(args.statistics);
            }
        },

        ///////////////////////////////////////////////////
        //// Utility Notifications

        // Helper function to refresh prediction panel after counter updates
        refreshPredictionPanelIfActive: function() {
            if (this.predictionPanelEnabled && document.getElementById('prediction_panel') && 
                document.getElementById('prediction_panel').style.display !== 'none') {
                this.updatePredictionPanel();
            }
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        ///////////////////////////////////////////////////
        //// Development statistics refresh functionality
        
        refreshDevStatistics: function() {
            console.log('Refreshing development statistics...');
            
            // Disable button and show loading
            const refreshBtn = document.getElementById('refresh_stats_btn');
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Loading...';
            }
            
            // Make AJAX call to get real statistics
            this.bgaPerformAction('actGetDevStatistics', {}, {
                onSuccess: () => {
                    console.log('Statistics refresh request successful');
                    // The actual data will come via notification handler
                    
                    // Re-enable button
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '🔄 Refresh Live Statistics';
                    }
                },
                onError: (error) => {
                    console.error('Failed to refresh statistics:', error);
                    
                    // Show error in stats panel
                    const liveStatsContent = document.getElementById('live_stats_content');
                    if (liveStatsContent) {
                        liveStatsContent.innerHTML = '<div style="color: #F44336;">Error loading statistics</div>';
                    }
                    
                    // Re-enable button
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '🔄 Refresh Live Statistics';
                    }
                }
            });
        },
        
        updateDevStatisticsWithServerData: function(stats) {
            try {
                // Update table statistics with real data
                const liveStatsContent = document.getElementById('live_stats_content');
                if (liveStatsContent && stats.table) {
                    const tableStats = stats.table;
                    liveStatsContent.innerHTML = `
                        <div style="margin-bottom: 3px; font-size: 21px;">Total Rounds: <span style="color: #4CAF50;">${tableStats.total_rounds || 0}</span></div>
                        <div style="margin-bottom: 3px; font-size: 21px;">Global Disasters Played: <span style="color: #4CAF50;">${tableStats.total_global_disasters || 0}</span></div>
                        <div style="margin-bottom: 3px; font-size: 21px;">Local Disasters Played: <span style="color: #4CAF50;">${tableStats.total_local_disasters || 0}</span></div>
                        <div style="margin-bottom: 3px; font-size: 21px;">Bonus Cards Played: <span style="color: #4CAF50;">${tableStats.total_bonus_cards || 0}</span></div>
                        <div style="margin-bottom: 3px; font-size: 21px;">Players Eliminated: <span style="color: #4CAF50;">${tableStats.players_eliminated || 0}</span></div>
                    `;
                }
                
                // Update player statistics with real data
                if (stats.players) {
                    const players = this.gamedatas.players || {};
                    let playerStatsHtml = '<div style="margin-bottom: 5px; color: #4CAF50; font-weight: bold; font-size: 21px;">✅ Player Statistics (live data)</div>';
                    
                    Object.values(players).forEach(player => {
                        const playerStats = stats.players[player.id];
                        if (playerStats) {
                            const playerColor = this.fixPlayerColor(player.color);
                            const isEliminated = (player.family === 0 && !player.chief);
                            const statusIcon = isEliminated ? '💀' : (player.chief ? '👑' : '👤');
                            
                            playerStatsHtml += `
                                <div style="margin-bottom: 8px; padding: 5px; background: rgba(255,255,255,0.05); border-radius: 3px; ${isEliminated ? 'opacity: 0.6;' : ''}">
                                    <div style="font-weight: bold; color: ${playerColor}; margin-bottom: 3px;">${statusIcon} ${player.name}</div>
                                    <div style="font-size: 20px; line-height: 1.3; color: #4CAF50;">
                                        Atheists Converted: <span style="color: #FFF;">${playerStats.atheists_converted || 0}</span> | Believers Converted: <span style="color: #FFF;">${playerStats.believers_converted || 0}</span><br>
                                        Families Lost: <span style="color: #FFF;">${playerStats.families_lost || 0}</span> | Temples Built: <span style="color: #FFF;">${playerStats.temples_built || 0}</span><br>
                                        Amulets Gained: <span style="color: #FFF;">${playerStats.amulets_gained || 0}</span> | Speeches Given: <span style="color: #FFF;">${playerStats.speeches_given || 0}</span><br>
                                        Cards Played: <span style="color: #FFF;">${playerStats.cards_played || 0}</span> | Disasters Doubled: <span style="color: #FFF;">${playerStats.global_disasters_doubled || 0}</span>
                                    </div>
                                    <div style="font-size: 20px; line-height: 1.3; margin-top: 3px; padding-top: 3px; border-top: 1px solid rgba(255,255,255,0.1);">
                                        <strong>Current State:</strong><br>
                                        Families: <span style="color: ${player.family > 0 ? '#4CAF50' : '#F44336'}">${player.family || 0}</span> | 
                                        Prayer: <span style="color: ${player.prayer > 5 ? '#4CAF50' : player.prayer > 2 ? '#FF9800' : '#F44336'}">${player.prayer || 0}</span><br>
                                        Happiness: <span style="color: ${player.happiness > 6 ? '#4CAF50' : player.happiness > 3 ? '#FF9800' : '#F44336'}">${player.happiness || 0}/10</span> | 
                                        Temples: <span style="color: #2196F3">${player.temple || 0}</span> | 
                                        Amulets: <span style="color: #9C27B0">${player.amulet || 0}</span><br>
                                        Leader: <span style="color: ${player.chief ? '#4CAF50' : '#F44336'}">${player.chief ? 'Yes' : 'No'}</span> | 
                                        Cards: <span style="color: ${player.cards > 3 ? '#4CAF50' : player.cards > 1 ? '#FF9800' : '#F44336'}">${player.cards || 0}</span>
                                        ${isEliminated ? '<br><span style="color: #F44336; font-weight: bold;">❌ ELIMINATED</span>' : ''}
                                    </div>
                                </div>
                            `;
                        }
                    });
                    
                    document.getElementById('player_stats_content').innerHTML = playerStatsHtml;
                }
                
            } catch (error) {
                console.error('Error updating statistics with server data:', error);
            }
        },

        ///////////////////////////////////////////////////
        //// Helper functions for development statistics panel
        
        getTotalFamilies: function(players) {
            return Object.values(players).reduce((total, player) => total + (parseInt(player.family) || 0), 0);
        },

        getTotalTemples: function(players) {
            return Object.values(players).reduce((total, player) => total + (parseInt(player.temple) || 0), 0);
        },

        getTotalAmulets: function(players) {
            return Object.values(players).reduce((total, player) => total + (parseInt(player.amulet) || 0), 0);
        },

        getAtheistCount: function() {
            return this.atheists ? this.atheists.count() : '?';
        },

    });
});