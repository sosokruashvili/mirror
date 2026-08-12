<?php

/*
| Material types stored in products.product_type and used by the warehouse.
| Distinct from order_type / product_type used on orders - that is a different
| vocabulary (mirror, glass, lamix, glass_pkg, service).
| Keys are stored DB values.
*/

return [
    'glass' => 'Glass',
    'film' => 'Film',
    'mirror' => 'Mirror',
    'butyl' => 'Butyl',
];
