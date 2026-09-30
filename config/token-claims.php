<?php

return [

    // Base IAM API URL; "/token/claims" is appended. Falls back to the
    // service's existing app.iam_service_url so current defaults keep working.
    'iam_url' => env('TOKEN_CLAIMS_IAM_URL'),

    // Seconds to wait for IAM before treating it as unavailable (503).
    'timeout' => (int) env('TOKEN_CLAIMS_TIMEOUT', 10),

    // Guard the role middleware checks when the route does not pass one.
    'guard' => null,

    // Name the required permissions in the 403 detail. Null follows
    // config('permission.display_permission_in_exception').
    'show_permissions_in_error' => null,

    // Translation keys for the error "detail". Point these at the service's
    // own keys (e.g. "auth.invalid_session") to keep existing wording.
    'messages' => [
        'invalid_session' => 'token-claims::token-claims.invalid_session',
        'session_service_unavailable' => 'token-claims::token-claims.session_service_unavailable',
        'unauthorized_action' => 'token-claims::token-claims.unauthorized_action',
        'permission_denied' => 'token-claims::token-claims.permission_denied',
        'permission_denied_detailed' => 'token-claims::token-claims.permission_denied_detailed',
    ],

];
