
-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
--  kalua implementation : ©  August Delemeester haphazardeinsteinaugdog@gmail.com
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

CREATE TABLE IF NOT EXISTS `disaster_card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(11) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `play_order` int(11) DEFAULT NULL,
  `played_by` int(11) DEFAULT NULL,
  `target_player` int(11) DEFAULT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `bonus_card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(11) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `play_order` int(11) DEFAULT NULL,
  `played_by` int(11) DEFAULT NULL,
  `target_player` int(11) DEFAULT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `player` 
ADD `player_first` BOOLEAN NOT NULL DEFAULT '0',
ADD `player_token_sprite` BOOLEAN NOT NULL DEFAULT '0',
ADD `player_happiness` INT(11) NOT NULL DEFAULT '5',
ADD `player_prayer` INT(11) NOT NULL DEFAULT '5',
ADD `player_family` INT(11) NOT NULL DEFAULT '0',
ADD `player_chief` BOOLEAN NOT NULL DEFAULT '1',
ADD `player_die` smallint(5) NOT NULL DEFAULT '0',
ADD `player_temple` INT(11) NOT NULL DEFAULT '0',
ADD `player_amulet` INT(11) NOT NULL DEFAULT '0',
ADD `player_card_count` INT(11) NOT NULL DEFAULT '0';

-- Table to track player choices for global disaster cards
-- choice: 'normal' (default), 'avoid', 'double'
CREATE TABLE IF NOT EXISTS `global_disaster_choice` (
  `card_id` int(10) unsigned NOT NULL,
  `player_id` int(11) NOT NULL,
  `choice` varchar(16) NOT NULL DEFAULT 'normal',
  `cost_paid` int(11) DEFAULT 0,
  PRIMARY KEY (`card_id`, `player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Initialize global variables
-- Note: This will only insert if the global_id doesn't already exist
-- The value will be properly set during game setup based on player count
INSERT IGNORE INTO `global` (global_id, global_value) VALUES (101, 0);
