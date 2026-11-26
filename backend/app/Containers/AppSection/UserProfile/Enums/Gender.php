<?php

namespace App\Containers\AppSection\UserProfile\Enums;

enum Gender: string
{
    case MALE = 'male';
    case FEMALE = 'female';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Мужчина',
            self::FEMALE => 'Женщина',
        };
    }
}
