<?php

declare(strict_types=1);

// Homepage advertisements. Array order is display order; no database is needed.
// Use named frontend routes and local image paths, not arbitrary HTML.
return [
    'enabled' => true,
    'autoplay' => true,
    'interval' => 6000, // Milliseconds; the carousel enforces a 5-second minimum.
    'slides' => [
        [
            'id' => 'audio-offers',
            'theme' => 'sky',
            'eyebrow' => 'FIND YOUR FREQUENCY',
            'title' => ['Less noise.', 'More you.'],
            'description' => 'Make room for better sound. Explore offers on everyday audio.',
            'label' => 'Audio offers',
            'image' => 'images/products/soundcore-space-2.jpg',
            'alt' => 'Soundcore Space 2 over-ear headphones',
            'cta' => 'Shop audio offers',
            'route' => 'shop',
            'parameters' => ['category' => 'audio', 'deals' => 1],
        ],
        [
            'id' => 'phone-edit',
            'theme' => 'lilac',
            'eyebrow' => 'THE NEXT CHAPTER',
            'title' => ['Your next', 'big thing.'],
            'description' => 'A fresh perspective in your pocket. Find your next everyday upgrade.',
            'label' => 'The phone edit',
            'image' => 'images/products/galaxy-s26-ultra.webp',
            'alt' => 'Samsung Galaxy S26 Ultra smartphone',
            'cta' => 'Explore phones',
            'route' => 'shop',
            'parameters' => ['category' => 'phones'],
        ],
        [
            'id' => 'charging-offers',
            'theme' => 'mint',
            'eyebrow' => 'READY FOR EVERY DAY',
            'title' => ['Small kit.', 'Big energy.'],
            'description' => 'Keep the good things going. Discover offers on charging essentials.',
            'label' => 'Charging offers',
            'image' => 'images/products/ugreen-uno-65w.png',
            'alt' => 'UGREEN Uno 65W charger',
            'cta' => 'Shop charging offers',
            'route' => 'shop',
            'parameters' => ['category' => 'charging', 'deals' => 1],
        ],
    ],
];
