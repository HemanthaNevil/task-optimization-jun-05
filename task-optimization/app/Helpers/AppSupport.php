<?php

namespace App\Helpers;

use App\Models\User;

class AppSupport
{
    public static function getCompanyOfAuthUser()
    {
        return User::first();
    }

    public static function createPDFWithIDandSelfie($idDocument, $selfieDocument, $idHash, $imageHash, $isVerified)
    {
        return true;
    }
}