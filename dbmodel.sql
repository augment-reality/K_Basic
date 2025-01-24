-- Create a "card" table to be used with the "Deck" tools:
-- card_type (global/local/bonus) 21 Bonus, 19 Local Disaster, 10 Global Disaster
-- card_type_arg(1,2,3)
-- card_location (deck, p1, p2, p3, p4, p5)
-- card_location_arg (0-5)

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- -- add a custom field to the standard "player" table
-- prayer cost
-- effects:

-- -- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

