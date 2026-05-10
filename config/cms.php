<?php

return [
    /*
    | When true, public IPs are resolved to country/region/city via ip-api.com (HTTP, cached 24h).
    | Disable in development or air-gapped environments.
    */
    'access_log_geo_lookup' => env('CMS_ACCESS_LOG_GEO_LOOKUP', true),
];
