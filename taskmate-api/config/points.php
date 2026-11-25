<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signup Bonus
    |--------------------------------------------------------------------------
    |
    | Points granted to every newly registered user. The bonus is recorded as a
    | ledger row so that a user's cached balance always matches the sum of
    | their point transactions.
    |
    */

    'signup_bonus' => (int) env('TASKMATE_SIGNUP_BONUS', 100),

];
