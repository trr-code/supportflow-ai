<?php

dataset('harbor retrieval slug coverage', [
    'unused 28L to 36L exchange' => [
        'Can I exchange an unused Harbor Trail Pack 28L for the 36L version free of charge?',
        ['exchanges', 'trail-pack-sizes'],
    ],
    '36L instead of 28L without the word exchange' => [
        'Can I get the 36L instead of my unused 28L Harbor Trail Pack?',
        ['exchanges', 'trail-pack-sizes'],
    ],
    'production 36L follow-up wording' => [
        'Can I exchange it for the 36L version instead?',
        ['exchanges'],
    ],
    'unused return without original box and prepaid label' => [
        'I have an unused Trail Pack with its tags, but no original box. Explain the return deadline, packaging requirements, prepaid-label process, and next steps.',
        ['return-window'],
    ],
    'return shipping and warranty together' => [
        'Explain the complete return, shipping, and warranty policies.',
        ['return-window', 'shipping-times', 'warranty'],
    ],
    'shipping returns ticket wants larger size and prepaid label' => [
        'The 28L Trail Pack I received is too small for a weekend trip. I want to exchange for 36L and need a prepaid return label.',
        ['exchanges', 'trail-pack-sizes', 'return-window'],
    ],
    'duffel embroidery without listed colors' => [
        'What thread colors are available for Driftwood Duffel embroidery?',
        ['duffel-care'],
    ],
]);
