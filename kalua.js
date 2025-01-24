
define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone",
],
function (dojo, declare) {
    return declare("bgagame.kalua", ebg.core.gamegui, {
        constructor: function(){
            console.log('kalua constructor');

            //use for other images to minimize load time?
            this.dontPreloadImage( 'd6.png' );

        // Zone control        	
        this.hkboard = new ebg.zone();

        //zone.create( this, 'happyblock_r', 64, 64 );
        //zone.setPattern( 'verticalfit' );
        //zone.placeInZone( this.happyblock_r, 1 );

        },
               
        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );

            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="game_board_Wrap">
                    <div id="board_background">
                        <div id="hkboard">
                        </div>
                    </div>	
                </div>

                `);

            //create a prayer counter for each player
            //var counter = new ebg.counter();
            //counter.create(pcounter);
            
            //try using stock to manage hk tokens
            this.hkstock = new ebg.stock();
            this.hkstock.create( this, $('game_board_Wrap'), 64, 64 );
            //args = page, div, width, height; change div location later

            // Specify that there are 10 images per row in the CSS sprite image
            this.hkstock.image_items_per_row = 10;
            
            //five players
            for( var color=1;color<=11;color++ )
                {
                    // Build token id
                    this.hkstock.addItemType( color, color, 'img/Cube_iso.png', color );
                    //args = id, weight for sorting purpose, URL of our CSS sprite, position of image in the CSS sprite.
                }
            //this.placeOnObject.

            // Example to add a div on the game area
            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="player-tables"></div>
            `);
            
            // Setting up player boards
            Object.values(gamedatas.players).forEach(player => {

                // example of setting up players boards
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', `
                    <div> Prayer count: <span id = "pc_loc"></span> <br> happiness: <br> cards here?</div>
                `);

                //rename div pc_loc with appended player id
                var ptable = ("pc_loc"+player.id);
                pc_loc.setAttribute("id", ptable);

                // example of adding a div for each player
                document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                    <div id= "player-table-"${player.id} >
                        <strong>${player.name}</strong>
                        <div> Setup player amulets, temples, etc here</div>
                    </div>
                `);
                
                //create counter per player in pc_loc div
                var counter = new ebg.counter();
                counter.create( document.getElementById(ptable));
                counter.setValue(5);
                
            });
            
            
            // TODO: Set up your game interface here, according to "gamedatas"
            
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       
        ///////////////////////////////////////////////////
        //// Game & client states// onEnteringState: this method is called each time we are entering into a new game state.

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName, args );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                 case 'playerTurn':    
                    const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

                    this.statusBar.addActionButton('Give a Speech');
                    this.statusBar.addActionButton('Convert Atheist');
                    this.statusBar.addActionButton('Convert Believer');
                    this.statusBar.addActionButton('Sacrifice Leader');
                    //  Add test action buttons in the action status bar, simulating a card click:
                    // playableCardsIds.forEach(
                    //     cardId => this.addActionButton(`actPlayCard${cardId}-btn`, _('Play card with id ${card_id}').replace('${card_id}', cardId), () => this.onCardClick(cardId))
                    //); 

                    this.addActionButton('actPass-btn', _('Pass'), () => this.bgaPerformAction("actPass"), null, null, 'gray'); 
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        ///////////////////////////////////////////////////
        //// Player's action

        // Example:
        
        onCardClick: function( card_id )
        {
            console.log( 'onCardClick', card_id );

            this.bgaPerformAction("actPlayCard", { 
                card_id,
            }).then(() =>  {                
                // What to do after the server call if it succeeded
                // (most of the time, nothing, as the game will react to notifs / change of state instead)
            });        
        },    

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your kalua.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            
            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            
            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 
        },  
        
        // TODO: from this point and below, you can write your game notifications handling methods

   });             
});
