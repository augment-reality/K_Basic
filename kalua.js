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
                    <div id="dice"></div>
                </div>

                <div id="player-tables" class="zone-container"></div>
            `);
        },
        
        setup: function(gamedatas) {
            console.log("Starting game setup");

            // Create player areas
            Object.values(gamedatas.players).forEach(player => {
                document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                    <div id="player_area_${player.id}" class="player_area">
                        <div class="player_name">${player.name}</div>
                        <div id="${player.id}_cards" class="player_cards"></div>
                        <div id="${player.id}_families" class="player_families"></div>
                    </div>
                `);
            });

            // Initialize meeples as a stock in each player's family div
            Object.values(gamedatas.players).forEach(player => {
                this[`fams_${player.id}`] = new ebg.stock();
                this[`fams_${player.id}`].create(this, $(`${player.id}_families`), 30, 30);
                this[`fams_${player.id}`].image_items_per_row = 10;

                // Make types for each color of meeple
                for (let i = 0; i < 10; i++) {
                    this[`fams_${player.id}`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png')
                    // addItemType(type: number, weight: number, image: string, image_position: number )
                }

                // Check gamedata for chief/family count
                const playerChief = player.chief;
                const playerFamily = player.family;

                for (let i = 0; i < playerFamily; i++) {
                    this[`fams_${player.id}`].addToStock(i, i);
                }
                
                // Get player color and set it to p_token_color
                if (playerChief > 0) {
                    this[`fams_${player.id}`].addToStock(1, 1);
                }
            });

            // Initialize and create atheist families stock
            this['atheists'] = new ebg.stock();
            this['atheists'].create(this, document.getElementById('atheistFamilies'), 30, 30);
            this['atheists'].setSelectionMode(0);
            this['atheists'].image_items_per_row = 10;
            for (let i = 0; i < 10; i++) {
                 this[`atheists`].addItemType(i, i, g_gamethemeurl + 'img/30_30_meeple.png', i);
            }

            //need to make this based on current state rather than initial state
            // Add three atheist families to hkboard for each player
            Object.values(gamedatas.players).forEach(player => {
                for (let i = 0; i < 3; i++) {
                    this['atheists'].addToStock(8);
                }
            });

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

            let hkTokenCount = 0;
            Object.values(gamedatas.players).forEach(player => {
                this[`hkToken_${5}`].addToStock(3);
                hkTokenCount++;
            });

            // this[`hkToken_${i}`].setSelectionMode(0);
            // this[`hkToken_${i}`].image_items_per_row = 1;
            // this[`hkToken_${i}`].container_div.width = "30px";
            // this[`hkToken_${i}`].autowidth = false; // this is required so it obeys the width set above
            // this[`hkToken_${i}`].use_vertical_overlap_as_offset = false; // this is to use normal vertical_overlap

            // this[`hkToken_${i}`].horizontal_overlap = -1; // current bug in stock - this is needed to enable z-index on overlapping items
            // this[`hkToken_${i}`].item_margin = 0; // has to be 0 if using overlap
            // this[`hkToken_${i}`].updateDisplay(); // re-layout

            this.dices = new ebg.stock();
            this.dices.create(this, document.getElementById('dice'), 50, 50);
            for (let i = 1; i <= 6; i++) {
                this.dices.addItemType(i, i, g_gamethemeurl + 'img/d6_300_50.png', i);
            }
            for (let i = 1; i <= 6; i++) {
                this.dices.addToStock(i);
            }

            // Create stock for played cards
            this['playedCards'] = new ebg.stock();  
            this['playedCards'].create(this, document.getElementById('playedCards'), 120, 174);
            this['playedCards'].image_items_per_row = 5;
            this['playedCards'].setSelectionMode(0);

            // Create disaster card types based on UniqueId and add to each player's card div
            for (let color = 1; color <= 3; color++) {
                for (let value = 1; value <= 5; value++) {
                    const card_type_id = this.getCardUniqueId(color, value);
                    Object.values(gamedatas.players).forEach(player => {
                        if (!this[`${player.id}_cards`]) {
                            this[`${player.id}_cards`] = new ebg.stock();
                            this[`${player.id}_cards`].create(this, document.getElementById(`${player.id}_cards`), 120, 174);
                            this[`${player.id}_cards`].image_items_per_row = 5;
                            this[`${player.id}_cards`].setSelectionMode(1);
                        }
                        this[`${player.id}_cards`].addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/Cards_Disaster_600_522.png', card_type_id);
                        this['playedCards'].addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/Cards_Disaster_600_522.png', card_type_id);
                    });
                }
            }

            // Setting up players' side panels
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
                counter_p.setValue(5);

                // Create happiness counter in player panel
                const counter_h = new ebg.counter();
                counter_h.create(document.getElementById(`panel_h_${player.id}`));
                counter_h.setValue(5);

                // Create card counter in player panel
                const counter_c = new ebg.counter();
                this[`counter_c_${player.id}`] = counter_c;
                counter_c.create(document.getElementById(`panel_c_${player.id}`));
                counter_c.setValue(0);
            });

            this.disaster_cards = new ebg.stock();
            Object.values(gamedatas.players).forEach(player => {
                this.disaster_cards.create(this, $(`${player.id}_cards`), 120, 174);
            });
            this.disaster_cards.image_items_per_row = 5;
            this.disaster_cards.setSelectionMode(1);

            // Initialize player hands
            Object.values(gamedatas.players).forEach(player => {
                this[`playerHand_${player.id}`] = new ebg.stock();
                this[`playerHand_${player.id}`].create(this, $(`${player.id}_cards`), 120, 174);
                this[`playerHand_${player.id}`].image_items_per_row = 5;
                this[`playerHand_${player.id}`].setSelectionMode(1);
            });

            // Cards in player's hand
            for (var i in this.gamedatas.hand) {
                var card = this.gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            //this.setupNotifications();

            console.log("Ending game setup");
        },
       
        ///////////////////////////////////////////////////
        //// Game & client states

        onEnteringState: function(stateName, args) {
            switch (stateName) {
                case 'Initial_Draw':
                    console.log("Entering Initial_Draw state");
                    this.addActionButton('drawDisasterCard-btn', _('Draw a Disaster Card'), () => {
                        this.drawDisasterCard();
                        const counter_c = this[`counter_c_${this.player_id}`];
                        counter_c.incValue(1);
                    });
                    break;
                case 'Free_Action':
                    console.log("Entering Free_Action state");
                    if(this.isCurrentPlayerActive()) {            
                        this.statusBar.addActionButton('Give a Speech');
                        this.statusBar.addActionButton('Convert Atheist');
                        this.statusBar.addActionButton('Convert Believer');
                        this.statusBar.addActionButton('Sacrifice Leader');        
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
            // Perform actions specific to leaving a state
        }, 

        onUpdateActionButtons: function(stateName, args) {
            // Make sure atheist count matches gamedata
            //this['hkboard'].addItemType(i, i, g_gamethemeurl + 'img/30_30_hktoken.png', i);
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        getCardUniqueId: function (color, value) {
            return (color - 1) * 5 + (value - 1);
        },

        drawDisasterCard: function() {
            console.log("Drawing a disaster card");

            // Simulate drawing a card from the deck
            const deckElement = document.getElementById('playedCards');
            const playerHandElement = document.getElementById(`${this.player_id}_cards`);

            if (deckElement && playerHandElement) {
                const card = this['playedCards'].getRandomItem(); // Get a random card from the deck
                if (card) {
                    this['playedCards'].removeFromStockById(card.id); // Remove card from the deck
                    this[`playerHand_${this.player_id}`].addToStockWithId(card.type, card.id); // Add card to player's hand
                    console.log(`Card ${card.id} added to player ${this.player_id}'s hand`);
                } else {
                    console.log("No cards left in the deck");
                }
            } else {
                console.error("Deck or player hand element not found");
            }
        },

/*         // Get parent zone of specified token (from can't stop)
        getParentZoneOfToken: function( node )
        {
            console.log( "getParentZoneOfToken" );
            var step_id = node.parentNode.id.substr( 5 );
            console.log( step_id );
            var ids = step_id.split( '_' );
            if( ids.length == 2 )
            {
                var column = ids[0];
                var height = ids[1];
                console.log( "column="+column+", height="+height );
                
                return this.columns[ column ][ height ];
            }
            else
            {
                console.log("getParentZoneOfToken failure");
                return null;
            }
        },
        
        // Place a token of the specified color on the specified place (column / height) (from can't stop)
        placeTokenOnColumn: function( color, column_id, height )
        {
            console.log( 'placeTokenOnColumn' );
            var token_id = 'token_'+color+'_'+column_id;
            var token_div = $(token_id);
            if( ! token_div )
            {
                // Create new token
                var origin = 'tokens';
                if( color === '000000' )
                {
                    var bhikkhu_id = this.bhikkhu_avail;
                    this.bhikkhu_avail --;
                    origin = 'bhikkhu_place_'+bhikkhu_id;
                    dojo.style( $('bhikkhu_'+bhikkhu_id), 'display', 'none' );
                }
                dojo.place( this.format_block('jstpl_token', {color:color,column:column_id} ), origin );
                this.columns[ column_id ][ height ].placeInZone( token_id, 0 );
            }
            else
            {
                // Move this token to position
                var parentZone = this.getParentZoneOfToken( token_div );                
                
                this.columns[ column_id ][ height ].placeInZone( token_id, 0 );
                
                // Remove it from precedent zone
                parentZone.removeFromZone( token_id, false );
            }
        }, */
        
        ///////////////////////////////////////////////////
        //// Player's action

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        // Put a token in some position (from cant stop)
        notif_saveprogression: function( notif )
        {
            console.log( 'notif_saveprogression' );
            console.log( notif );
            
            var color = this.gamedatas.players[ notif.args.player_id ].color;
            this.placeTokenOnColumn( color, notif.args.column_id, notif.args.height );
        },     

        // TODO: from this point and below, you can write your game notifications handling methods
    });
});