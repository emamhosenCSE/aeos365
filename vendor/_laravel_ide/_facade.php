<?php

namespace Illuminate\Support\Facades;

interface Auth
{
    /**
     * @return \Aero\Platform\Models\LandlordUser|false
     */
    public static function loginUsingId(mixed $id, bool $remember = false);

    /**
     * @return \Aero\Platform\Models\LandlordUser|false
     */
    public static function onceUsingId(mixed $id);

    /**
     * @return \Aero\Platform\Models\LandlordUser|null
     */
    public static function getUser();

    /**
     * @return \Aero\Platform\Models\LandlordUser
     */
    public static function authenticate();

    /**
     * @return \Aero\Platform\Models\LandlordUser|null
     */
    public static function user();

    /**
     * @return \Aero\Platform\Models\LandlordUser|null
     */
    public static function logoutOtherDevices(string $password);

    /**
     * @return \Aero\Platform\Models\LandlordUser
     */
    public static function getLastAttempted();
}