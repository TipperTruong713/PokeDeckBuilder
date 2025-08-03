Create DATABASE IF NOT EXISTS PokeDeckBuilder;
USE PokeDeckBuilder;

CREATE TABLE IF NOT EXISTS User (
    User_ID INT NOT NULL AUTO_INCREMENT,
    Profile_picture VARCHAR(255) NOT NULL,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    CONSTRAINT userPK PRIMARY KEY (User_ID)
);

CREATE TABLE IF NOT EXISTS Pack (
	Pack_ID INT NOT NULL AUTO_INCREMENT,
	Pack_Name VARCHAR(100) NOT NULL,
	Pack_Image VARCHAR(255),
	PRIMARY KEY (Pack_ID)
);

CREATE TABLE IF NOT EXISTS Card (
	Card_ID INT NOT NULL AUTO_INCREMENT,
	Card_Name VARCHAR(100) NOT NULL,
	Rarity VARCHAR(50) NOT NULL,
	Artist VARCHAR(100),
	Card_Type VARCHAR(50) NOT NULL,
	Card_Image VARCHAR(255),
	Card_Description TEXT,
	PRIMARY KEY (Card_ID)
);

CREATE TABLE IF NOT EXISTS Pokemon_Card (
	Card_ID INT NOT NULL,
	Stage VARCHAR(50),
	Ability VARCHAR(100),
	Type VARCHAR(50) NOT NULL,
	Weakness VARCHAR(50),
	HP INT,
	Retreat_Cost INT DEFAULT 0,
	PRIMARY KEY (Card_ID),
	FOREIGN KEY (Card_ID) REFERENCES Card(Card_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Pokemon_Card_Attack (
	Card_ID INT NOT NULL,
	Attack_Name VARCHAR(100) NOT NULL,
	Attack_Damage INT DEFAULT 0,
	Attack_Description TEXT,
	PRIMARY KEY (Card_ID, Attack_Name),
	FOREIGN KEY (Card_ID) REFERENCES Card(Card_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Trainer_Card (
	Card_ID INT NOT NULL,
	Trainer_Card_Type VARCHAR(50) NOT NULL,
	PRIMARY KEY (Card_ID),
	FOREIGN KEY (Card_ID) REFERENCES Card(Card_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Collection (
	Collection_ID INT NOT NULL AUTO_INCREMENT,
	User_ID INT NOT NULL,
	PRIMARY KEY (Collection_ID),
	FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Collections_Cards (
	Collection_ID INT NOT NULL,
	Card_ID INT NOT NULL,
	Quantity_In_Collection INT NOT NULL DEFAULT 1,
	PRIMARY KEY (Collection_ID, Card_ID),
	FOREIGN KEY (Collection_ID) REFERENCES Collection(Collection_ID) ON DELETE
	CASCADE,
	FOREIGN KEY (Card_ID) REFERENCES Card(Card_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Deck (
	Deck_ID INT NOT NULL AUTO_INCREMENT,
	Deck_Name VARCHAR(100) NOT NULL,
	Deck_Type VARCHAR(30),
	Deck_Description TEXT,
	User_ID INT NOT NULL,
	PRIMARY KEY (Deck_ID),
	FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Deck_Cards (
	Deck_ID INT NOT NULL,
	Card_ID INT NOT NULL,
	Quantity_In_Deck INT NOT NULL DEFAULT 1,
	PRIMARY KEY (Deck_ID, Card_ID),
	FOREIGN KEY (Deck_ID) REFERENCES Deck(Deck_ID) ON DELETE CASCADE,
	FOREIGN KEY (Card_ID) REFERENCES Card(Card_ID) ON DELETE CASCADE,
	CHECK (Quantity_In_Deck <= 4)
);

-- Sample Pokemon Cards for Testing
INSERT INTO card (Card_ID, Card_Name, Rarity, Artist, Card_Type, Card_Image, Card_Description) VALUES
('001', 'Charizard', 'rare', 'Ken Sugimori', 'pokemon', 'charizard.jpg', 'A powerful Fire-type Pokemon'),
('002', 'Blastoise', 'rare', 'Ken Sugimori', 'pokemon', 'blastoise.jpg', 'A powerful Water-type Pokemon'),
('003', 'Venusaur', 'rare', 'Ken Sugimori', 'pokemon', 'venusaur.jpg', 'A powerful Grass-type Pokemon'),
('004', 'Pikachu', 'common', 'Atsuko Nishida', 'pokemon', 'pikachu.jpg', 'An Electric-type Mouse Pokemon'),
('005', 'Squirtle', 'common', 'Ken Sugimori', 'pokemon', 'squirtle.jpg', 'A Water-type Turtle Pokemon'),
('006', 'Alakazam', 'rare', 'Ken Sugimori', 'pokemon', 'alakazam.jpg', 'A powerful Psychic-type Pokemon'),
('007', 'Machamp', 'rare', 'Ken Sugimori', 'pokemon', 'machamp.jpg', 'A Fighting-type Pokemon'),
('008', 'Gengar', 'rare', 'Ken Sugimori', 'pokemon', 'gengar.jpg', 'A Ghost-type Pokemon'),
('009', 'Dragonite', 'rare', 'Ken Sugimori', 'pokemon', 'dragonite.jpg', 'A Dragon-type Pokemon'),
('010', 'Eevee', 'uncommon', 'Ken Sugimori', 'pokemon', 'eevee.jpg', 'A Normal-type Evolution Pokemon');

INSERT INTO pokemon_card (Card_ID, Stage, Ability, Type, Weakness, HP, Retreat_Cost) VALUES
('001', 'Stage 2', 'Fire Spin', 'fire', 'water', 120, 3),
('002', 'Stage 2', 'Hydro Pump', 'water', 'electric', 170, 3),
('003', 'Stage 2', 'Solar Beam', 'grass', 'fire', 100, 2),
('004', 'Basic', 'Thunder Shock', 'electric', 'fighting', 60, 1),
('005', 'Basic', 'Water Gun', 'water', 'electric', 40, 1),
('006', 'Stage 2', 'Psychic', 'psychic', 'psychic', 80, 3),
('007', 'Stage 2', 'Seismic Toss', 'fighting', 'psychic', 100, 3),
('008', 'Stage 1', 'Shadow Ball', 'psychic', 'dark', 60, 1),
('009', 'Stage 2', 'Dragon Rush', 'dragon', 'fairy', 140, 2),
('010', 'Basic', 'Tail Whip', 'normal', 'fighting', 50, 1);