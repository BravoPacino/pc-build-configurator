DROP DATABASE IF EXISTS pc_build_configurator;
CREATE DATABASE pc_build_configurator
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE pc_build_configurator;

CREATE TABLE `user` (
  user_id        INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(50)  NOT NULL,
  email          VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_user_email UNIQUE (email)
) ENGINE=InnoDB;

CREATE TABLE category (
  category_id    INT AUTO_INCREMENT PRIMARY KEY,
  category_name  VARCHAR(30)      NOT NULL,
  max_quantity   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_required    TINYINT(1)       NOT NULL DEFAULT 1,
  display_order  TINYINT UNSIGNED NOT NULL,
  CONSTRAINT uq_category_name UNIQUE (category_name),
  CONSTRAINT ck_category_max_quantity CHECK (max_quantity >= 1)
) ENGINE=InnoDB;

CREATE TABLE component (
  component_id  INT AUTO_INCREMENT PRIMARY KEY,
  category_id   INT           NOT NULL,
  name          VARCHAR(120)  NOT NULL,
  brand         VARCHAR(50)   NOT NULL,
  price         DECIMAL(10,2) NOT NULL,
  stock_qty     INT           NOT NULL DEFAULT 0,
  reserved_qty  INT           NOT NULL DEFAULT 0,
  wattage       INT           NOT NULL DEFAULT 0,
  capacity_gb   INT           NULL,
  socket        VARCHAR(20)   NULL,
  memory_type   VARCHAR(10)   NULL,
  has_integrated_graphics TINYINT(1) NULL,
  includes_cooler         TINYINT(1) NULL,
  memory_slots  TINYINT UNSIGNED NULL,
  m2_slots      TINYINT UNSIGNED NULL,
  sata_ports    TINYINT UNSIGNED NULL,
  drive_bays    TINYINT UNSIGNED NULL,
  image_url     VARCHAR(255)  NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT uq_component_category_name UNIQUE (category_id, name),
  CONSTRAINT fk_component_category FOREIGN KEY (category_id)
    REFERENCES category (category_id) ON DELETE RESTRICT,
  CONSTRAINT ck_component_price    CHECK (price >= 0),
  CONSTRAINT ck_component_stock    CHECK (stock_qty >= 0),
  CONSTRAINT ck_component_reserved CHECK (reserved_qty >= 0),
  CONSTRAINT ck_component_wattage  CHECK (wattage >= 0),
  CONSTRAINT ck_component_graphics CHECK (has_integrated_graphics IN (0, 1)),
  CONSTRAINT ck_component_cooler   CHECK (includes_cooler IN (0, 1))
) ENGINE=InnoDB;

CREATE INDEX ix_component_category ON component (category_id, is_active);

CREATE TABLE compatibility_rule (
  rule_id        INT AUTO_INCREMENT PRIMARY KEY,
  rule_name      VARCHAR(100) NOT NULL,
  rule_type      ENUM('ATTRIBUTE_MATCH','CAPACITY_CHECK','REQUIRES_CATEGORY') NOT NULL,
  category_a     INT          NOT NULL,
  category_b     INT          NULL,
  attribute_key  VARCHAR(30)  NOT NULL,
  headroom_pct   DECIMAL(5,2) NULL,
  error_message  VARCHAR(255) NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT ck_rule_headroom CHECK (
    headroom_pct IS NULL
    OR (rule_type = 'CAPACITY_CHECK' AND headroom_pct > 0 AND headroom_pct <= 100)),
  CONSTRAINT ck_rule_category_b CHECK ((rule_type = 'CAPACITY_CHECK') = (category_b IS NULL)),
  CONSTRAINT fk_rule_category_a FOREIGN KEY (category_a)
    REFERENCES category (category_id) ON DELETE RESTRICT,
  CONSTRAINT fk_rule_category_b FOREIGN KEY (category_b)
    REFERENCES category (category_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE build (
  build_id       INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT           NOT NULL,
  build_name     VARCHAR(100)  NOT NULL,
  total_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_wattage  INT           NOT NULL DEFAULT 0,
  is_valid       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_build_user_name UNIQUE (user_id, build_name),
  CONSTRAINT fk_build_user FOREIGN KEY (user_id)
    REFERENCES `user` (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE build_item (
  build_item_id  INT AUTO_INCREMENT PRIMARY KEY,
  build_id       INT              NOT NULL,
  component_id   INT              NOT NULL,
  quantity       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT uq_build_item UNIQUE (build_id, component_id),
  CONSTRAINT fk_build_item_build FOREIGN KEY (build_id)
    REFERENCES build (build_id) ON DELETE CASCADE,
  CONSTRAINT fk_build_item_component FOREIGN KEY (component_id)
    REFERENCES component (component_id) ON DELETE RESTRICT,
  CONSTRAINT ck_build_item_quantity CHECK (quantity >= 1)
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT           NOT NULL,
  build_id      INT           NOT NULL,
  status        ENUM('pending','approved','rejected','cancelled')
                              NOT NULL DEFAULT 'pending',
  total_price   DECIMAL(10,2) NOT NULL,
  admin_remark  VARCHAR(255)  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES `user` (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_build FOREIGN KEY (build_id)
    REFERENCES build (build_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX ix_orders_status ON orders (status);

CREATE TABLE order_item (
  order_item_id        INT AUTO_INCREMENT PRIMARY KEY,
  order_id             INT              NOT NULL,
  component_id         INT              NOT NULL,
  quantity             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price_snapshot  DECIMAL(10,2)    NOT NULL,
  CONSTRAINT uq_order_item UNIQUE (order_id, component_id),
  CONSTRAINT fk_order_item_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_order_item_component FOREIGN KEY (component_id)
    REFERENCES component (component_id) ON DELETE RESTRICT,
  CONSTRAINT ck_order_item_quantity CHECK (quantity >= 1),
  CONSTRAINT ck_order_item_price    CHECK (unit_price_snapshot >= 0)
) ENGINE=InnoDB;

CREATE TABLE quotation_request (
  request_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT          NOT NULL,
  build_id       INT          NULL,
  contact_phone  VARCHAR(20)  NOT NULL,
  message        TEXT         NOT NULL,
  status         ENUM('new','answered','closed') NOT NULL DEFAULT 'new',
  admin_reply    TEXT         NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quotation_user FOREIGN KEY (user_id)
    REFERENCES `user` (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_quotation_build FOREIGN KEY (build_id)
    REFERENCES build (build_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO category (category_name, max_quantity, is_required, display_order) VALUES
  ('Motherboard', 1, 1, 1),
  ('CPU',         1, 1, 2),
  ('Cooler',      1, 0, 3),
  ('RAM',         2, 1, 4),
  ('GPU',         1, 0, 5),
  ('Storage',     4, 1, 6),
  ('Case',        1, 1, 7),
  ('PSU',         1, 1, 8);

INSERT INTO compatibility_rule
  (rule_name, rule_type, category_a, category_b, attribute_key,
   headroom_pct, error_message)
VALUES
  ('CPU socket must match the motherboard',
   'ATTRIBUTE_MATCH',
   (SELECT category_id FROM category WHERE category_name = 'CPU'),
   (SELECT category_id FROM category WHERE category_name = 'Motherboard'),
   'socket',
   NULL,
   'The CPU socket does not match the motherboard.'),

  ('Memory type must match the motherboard',
   'ATTRIBUTE_MATCH',
   (SELECT category_id FROM category WHERE category_name = 'RAM'),
   (SELECT category_id FROM category WHERE category_name = 'Motherboard'),
   'memory_type',
   NULL,
   'The memory type is not supported by this motherboard.'),

  ('Total power must stay within the safe load of the power supply',
   'CAPACITY_CHECK',
   (SELECT category_id FROM category WHERE category_name = 'PSU'),
   NULL,
   'wattage',
   80.00,
   'The components draw more power than the chosen power supply can safely provide.'),

  ('A processor without integrated graphics needs a graphics card',
   'REQUIRES_CATEGORY',
   (SELECT category_id FROM category WHERE category_name = 'CPU'),
   (SELECT category_id FROM category WHERE category_name = 'GPU'),
   'has_integrated_graphics',
   NULL,
   'This processor has no integrated graphics, so the build needs a graphics card.'),

  ('A processor sold without a cooler needs one',
   'REQUIRES_CATEGORY',
   (SELECT category_id FROM category WHERE category_name = 'CPU'),
   (SELECT category_id FROM category WHERE category_name = 'Cooler'),
   'includes_cooler',
   NULL,
   'This processor comes without a cooler, so the build needs a CPU cooler.'),

  ('Memory sticks must fit the memory slots',
   'CAPACITY_CHECK',
   (SELECT category_id FROM category WHERE category_name = 'Motherboard'),
   NULL,
   'memory_slots',
   NULL,
   'The motherboard does not have enough memory slots for this memory.'),

  ('NVMe drives must fit the M.2 slots',
   'CAPACITY_CHECK',
   (SELECT category_id FROM category WHERE category_name = 'Motherboard'),
   NULL,
   'm2_slots',
   NULL,
   'The motherboard does not have enough M.2 slots for these drives.'),

  ('Hard disks must fit the SATA ports',
   'CAPACITY_CHECK',
   (SELECT category_id FROM category WHERE category_name = 'Motherboard'),
   NULL,
   'sata_ports',
   NULL,
   'The motherboard does not have enough SATA ports for these drives.'),

  ('Hard disks must fit the drive bays',
   'CAPACITY_CHECK',
   (SELECT category_id FROM category WHERE category_name = 'Case'),
   NULL,
   'drive_bays',
   NULL,
   'The case does not have enough drive bays for these hard disks.');

INSERT INTO `user` (username, email, password_hash, role) VALUES
  ('Administrator', 'admin@pcbuild.local',
   '$2y$12$aiV8PuFNf9VKwmlfHGMXiuvWzWWNvB/fBKFyxAyU3HeSKlgrlp9mu', 'admin');

