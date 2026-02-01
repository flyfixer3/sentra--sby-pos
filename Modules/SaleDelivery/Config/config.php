<?php

return [
    'name' => 'SaleDelivery',

    // ✅ Dynamic options (bukan hardcode di blade)
    // Kamu bisa pindahkan ini ke DB kapan saja, tapi untuk sekarang source-nya jelas & terpusat.
    'defect_types' => [
        'bubble',
        'scratch',
        'distortion',
        'chip',
        'crack',
        'edge_defect',
        'coating_issue',
        'dimension_issue',
        'other',
    ],

    'damaged_reasons' => [
        'broken_on_arrival',
        'broken_during_handling',
        'broken_during_installation',
        'impact_damage',
        'shipping_damage',
        'unknown',
    ],
];
