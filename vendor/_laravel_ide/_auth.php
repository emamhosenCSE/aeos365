<?php

namespace Illuminate\Contracts\Auth;

interface Guard
{
    /**
     * @return \Aero\Platform\Models\LandlordUser|null
     */
    public function user();
}