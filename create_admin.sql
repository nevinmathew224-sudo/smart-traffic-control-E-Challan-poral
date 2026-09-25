USE traffic_system;

INSERT INTO users (email, password, role)
VALUES ('admin@traffic.com', '$2y$10$PY9DmREk05yrFEhFzfIOs.geuIaOhq7da2Hd6/X3I.O22dK8i.PtK', 'admin')
ON DUPLICATE KEY UPDATE
password = VALUES(password),
role = VALUES(role);
