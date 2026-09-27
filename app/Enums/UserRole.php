<?php

namespace App\Enums;

enum UserRole: string
{
    case STUDENT = 'student';
    case CR = 'cr';
    case TEACHER = 'teacher';
}
