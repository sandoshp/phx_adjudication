
-- Generate hash: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT), PHP_EOL;"

--PHP commandline

M:\>php -r "echo password_hash('hlab5701', PASSWORD_BCRYPT), PHP_EOL;
$2y$10$6Wv66FeSZk7ZXCHHS7JadOCsMd3NLYp915PQUZWDkjZHhXB.uVpDe

M:\>php -r "echo password_hash('cyp2d6', PASSWORD_BCRYPT), PHP_EOL;
$2y$10$Ts8qLMu6IQtGcWhKJO4aLO6L3MSLcRFVRuxy8AYDvW9ugFiQNBzSW

M:\>php -r "echo password_hash('cyp2c19', PASSWORD_BCRYPT), PHP_EOL;
$2y$10$BFQACw9SbRZ/ZjWbkCpjjenKKmvh0CTCJd.Q9f.3TUlHNUvN/9mxq

M:\>php -r "echo password_hash('slco1b1', PASSWORD_BCRYPT), PHP_EOL;
$2y$10$gBC/keIoQ6uSf.BEFL5zUesZx0USpZEygCxQjPlbCDl/ByfFhW9nK



--MySQL
INSERT INTO users (email, name, role, password_hash)
VALUES
  ('admin@phoenix.gla.ac.uk','Admin','admin','$2y$10$6Wv66FeSZk7ZXCHHS7JadOCsMd3NLYp915PQUZWDkjZHhXB.uVpDe'),
  ('sandosh.padmanabhan@glasgow.ac.uk','sandosh','adjudicator','$2y$10$Ts8qLMu6IQtGcWhKJO4aLO6L3MSLcRFVRuxy8AYDvW9ugFiQNBzSW'),
  ('stefanie.lip@glasgow.ac.uk','stefanie','adjudicator','$2y$10$BFQACw9SbRZ/ZjWbkCpjjenKKmvh0CTCJd.Q9f.3TUlHNUvN/9mxq'),
  ('iain.frater@glasgow.ac.uk','iain','adjudicator','$2y$10$gBC/keIoQ6uSf.BEFL5zUesZx0USpZEygCxQjPlbCDl/ByfFhW9nK'),
  ('chair@phoenix.gla.ac.uk','Chair','chair','$2y$10$6Wv66FeSZk7ZXCHHS7JadOCsMd3NLYp915PQUZWDkjZHhXB.uVpDe');
  
  
