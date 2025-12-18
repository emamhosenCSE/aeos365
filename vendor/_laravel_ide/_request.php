<?php

namespace Illuminate\Http;

interface Request
{
    /**
     * @return \Aero\Platform\Models\LandlordUser|null
     */
    public function user($guard = null);
}