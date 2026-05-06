<?php

namespace App\ERP\Shared\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Posted = 'posted';
    case Void = 'void';
}
