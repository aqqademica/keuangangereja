USE gereja_db;
DELETE FROM user_security_answers;
DELETE FROM security_questions;
ALTER TABLE security_questions AUTO_INCREMENT = 1;

INSERT INTO security_questions (question_text) VALUES 
('Ayat Alkitab Favorit anda (namakitab:pasal:ayat) (*tanpa spasi)'),
('Pastor / Pendeta / Penghkhotbah / Gembala Favorit Anda'),
('Tokoh Alkitab yang anda Kagumi selain selain Yesus, Murid Yesus, dan Nabi-nabi.');
