<?php

namespace App;

/**
 * User roles. Owner is unique (created via seeder only).
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Partner = 'partner';
}
