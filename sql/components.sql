USE pc_build_configurator;

SET @cpu     = (SELECT category_id FROM category WHERE category_name = 'CPU');
SET @mb      = (SELECT category_id FROM category WHERE category_name = 'Motherboard');
SET @ram     = (SELECT category_id FROM category WHERE category_name = 'RAM');
SET @gpu     = (SELECT category_id FROM category WHERE category_name = 'GPU');
SET @storage = (SELECT category_id FROM category WHERE category_name = 'Storage');
SET @psu     = (SELECT category_id FROM category WHERE category_name = 'PSU');
SET @cooler  = (SELECT category_id FROM category WHERE category_name = 'Cooler');
SET @case    = (SELECT category_id FROM category WHERE category_name = 'Case');

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, socket, has_integrated_graphics, includes_cooler) VALUES
  (@cpu, 'Ryzen 9 9950X3D',    'AMD',   3048.21,  4, 230, 'AM5',     1, 0),
  (@cpu, 'Ryzen 7 9800X3D',    'AMD',   2940.31,  7, 162, 'AM5',     1, 0),
  (@cpu, 'Ryzen 7 7800X3D',    'AMD',   1291.00,  5, 162, 'AM5',     1, 0),
  (@cpu, 'Ryzen 5 9600X',      'AMD',    593.00, 12,  88, 'AM5',     1, 0),
  (@cpu, 'Ryzen 5 8600G',      'AMD',   1079.00,  9,  88, 'AM5',     1, 1),
  (@cpu, 'Core Ultra 9 285K',  'Intel', 2889.81,  2, 250, 'LGA1851', 1, 0),
  (@cpu, 'Core i9-14900K',     'Intel', 2305.71,  6, 253, 'LGA1700', 1, 0),
  (@cpu, 'Core i5-14400F',     'Intel',  796.00, 14, 148, 'LGA1700', 0, 1);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, socket, memory_type, memory_slots, m2_slots, sata_ports) VALUES
  (@mb, 'PRO X870E-S EVO WIFI',          'MSI',      1179.09,  5, 40, 'AM5',     'DDR5', 4, 3, 4),
  (@mb, 'MAG B850M MORTAR WIFI',         'MSI',      1080.09,  8, 40, 'AM5',     'DDR5', 4, 3, 4),
  (@mb, 'B850 AORUS STEALTH BLACK',      'Gigabyte', 1599.00,  3, 40, 'AM5',     'DDR5', 4, 4, 2),
  (@mb, 'TUF GAMING Z890-PLUS WIFI',     'ASUS',      898.97,  4, 40, 'LGA1851', 'DDR5', 4, 4, 4),
  (@mb, 'TUF GAMING B760M-PLUS WIFI D4', 'ASUS',      647.05,  6, 40, 'LGA1700', 'DDR4', 4, 2, 4),
  (@mb, 'H610M K DDR4',                  'Gigabyte',  278.19, 11, 40, 'LGA1700', 'DDR4', 2, 1, 2);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, capacity_gb, memory_type, memory_slots) VALUES
  (@ram, 'NOX DDR4 8GB 3200',                    'Apacer',   90.00, 20,  5,  8, 'DDR4', 1),
  (@ram, 'DDR4 Desktop 16GB 3200 CL22',          'Lexar',   238.59, 16,  5, 16, 'DDR4', 1),
  (@ram, 'AEGIS DDR4 16GB 3200',                 'G.SKILL', 429.00, 10,  5, 16, 'DDR4', 1),
  (@ram, 'DDR5 U-DIMM 16GB 5600',                'ADATA',   689.00,  9,  5, 16, 'DDR5', 1),
  (@ram, 'Vengeance RGB DDR5 32GB (2x16GB) 6000 CL36', 'Corsair', 3032.00, 4, 10, 32, 'DDR5', 2),
  (@ram, 'Vengeance RGB DDR5 64GB (2x32GB) 6000 CL30', 'Corsair', 6702.00, 2, 10, 64, 'DDR5', 2);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, capacity_gb) VALUES
  (@gpu, 'GeForce RTX 5060 OC Low Profile 8GB', 'Gigabyte', 1662.21, 8, 145,  8),
  (@gpu, 'Dual Radeon RX 9060 XT 16GB',         'ASUS',     2315.61, 6, 160, 16),
  (@gpu, 'GeForce RTX 5060 Ti 8G',              'MSI',      2931.45, 4, 180,  8),
  (@gpu, 'PRIME Radeon RX 9070 EVO OC 16GB',    'ASUS',     3696.00, 3, 220, 16),
  (@gpu, 'GeForce RTX 5070 GAMING TRIO OC 12GB','MSI',      4946.00, 1, 250, 12),
  (@gpu, 'GeForce RTX 5070 Ti INSPIRE 3X OC 16GB', 'MSI',  6999.00, 3, 300, 16),
  (@gpu, 'GeForce RTX 5080 INSPIRE 3X OC 16GB',  'MSI',     8899.00, 2, 360, 16),
  (@gpu, 'GAMING GeForce RTX 5090 SOLID OC 32GB', 'ZOTAC', 26999.00, 1, 575, 32),
  (@gpu, 'GeForce RTX 5090 GAMING TRIO OC 32GB', 'MSI',    28399.00, 1, 575, 32);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, capacity_gb, m2_slots, sata_ports, drive_bays) VALUES
  (@storage, 'BarraCuda 2TB SATA HDD',          'Seagate',         265.00, 13,  9, 2000, 0, 1, 1),
  (@storage, 'P3 Plus 1TB PCIe Gen4 NVMe',      'Crucial',         315.00, 15,  6, 1000, 1, 0, 0),
  (@storage, '990 PRO 1TB PCIe Gen4 NVMe',      'Samsung',         549.00, 10,  7, 1000, 1, 0, 0),
  (@storage, 'SN850X 2TB PCIe Gen4 NVMe',       'Western Digital', 859.00,  7,  7, 2000, 1, 0, 0),
  (@storage, 'N300 NAS 4TB SATA HDD',           'Toshiba',        1490.41,  2, 10, 4000, 0, 1, 1);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage) VALUES
  (@psu, 'Gamma P550 550W',               'Ocypus',        159.00, 11,  550),
  (@psu, 'AP Series 750W',                'AIGO',          179.00, 10,  750),
  (@psu, 'GAMERSTORM PFX PF500X 500W',    'DeepCool',      179.00,  8,  500),
  (@psu, 'C750 750W Bronze ATX3.1',       'NZXT',          299.00,  7,  750),
  (@psu, 'PQ1200G 1200W Fully Modular',   'DeepCool',      749.00,  2, 1200),
  (@psu, 'GP-P650SS 650W',                'Gigabyte',      218.00, 12,  650),
  (@psu, 'MAG A650BN 650W',               'MSI',           256.00,  9,  650),
  (@psu, 'CX550 550W 80+ Bronze',         'Corsair',       260.00, 14,  550),
  (@psu, 'P650G PG5 650W ATX3.1',         'Gigabyte',      349.00,  7,  650),
  (@psu, 'MWE GOLD V3 850W ATX3.1',       'Cooler Master', 349.00,  5,  850),
  (@psu, 'RM850e 850W 80+ Gold',          'Corsair',       535.59,  6,  850),
  (@psu, 'RM1000E 1000W ATX3.1',          'Corsair',       599.00,  3, 1000);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage) VALUES
  (@cooler, 'Phantom Spirit 120 SE',    'Thermalright', 170.00, 12, 5),
  (@cooler, 'Peerless Assassin 120 SE', 'Thermalright', 180.00, 15, 5),
  (@cooler, 'AK620',                    'DeepCool',     280.00,  8, 3),
  (@cooler, 'Dark Rock Pro 5',          'be quiet!',    400.00,  5, 6),
  (@cooler, 'NH-D15 G2',                'Noctua',       700.00,  2, 5);

INSERT INTO component
  (category_id, name, brand, price, stock_qty, wattage, drive_bays) VALUES
  (@case, 'CH560 Black',       'DeepCool', 500.00, 6, 12, 2),
  (@case, 'CH560 White',       'DeepCool', 600.00, 4, 12, 2),
  (@case, 'LANCOOL 216',       'Lian Li',  500.00, 7,  9, 2),
  (@case, '4000D AIRFLOW',     'Corsair',  500.00, 9,  6, 2),
  (@case, 'AIR 903 MAX White', 'Montech',  510.00, 3, 12, 2),
  (@case, 'AIR 903 MAX Black', 'Montech',  520.00, 5, 12, 2);
