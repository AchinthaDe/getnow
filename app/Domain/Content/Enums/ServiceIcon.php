<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ServiceIcon: string
{
    case TRUCK = 'truck';
    case BOX = 'box';
    case SHIELD = 'shield';
    case HEADPHONES = 'headphones';
    case BOLT = 'bolt';
    case CHECK = 'check';
    case LOCK = 'lock';
    case HEART = 'heart';
    case USER = 'user';
    case WATCH = 'watch';
    case PHONE = 'phone';
    case SPEAKER = 'speaker';
    case BATTERY = 'battery';
    case GAMEPAD = 'gamepad';
    case HOME = 'home';
    case LAPTOP = 'laptop';
    case MAIL = 'mail';
    case SEARCH = 'search';
    case BAG = 'bag';
}
