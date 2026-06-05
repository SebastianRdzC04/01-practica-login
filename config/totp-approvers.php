<?php

return [
    'approvers' => [
        [
            'name' => env('APPROVER_1_NAME', 'Approver 1'),
            'secret' => env('APPROVER_1_SECRET'),
        ],
        [
            'name' => env('APPROVER_2_NAME', 'Approver 2'),
            'secret' => env('APPROVER_2_SECRET'),
        ],
    ],
];
