<?php

namespace App\Data;

class ScenarioTimetableData
{
    public static function getTimetableForScenario(int $scenarioId): array
    {
        $timetables = [
            1 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ]
                ]
            ],
            2 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'tuesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'wednesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'thursday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'friday' => ['working_hours' => ['start' => '08:00', 'end' => '18:00']],
                    ]
                ]
            ],
            3 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'tuesday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'wednesday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'thursday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'friday' => ['working_hours' => ['start' => '07:00', 'end' => '22:00']],
                        'saturday' => ['working_hours' => ['start' => '09:00', 'end' => '18:00']],
                        'sunday' => ['working_hours' => ['start' => '09:00', 'end' => '16:00']],
                    ]
                ]
            ],
            4 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                            'breaks' => [['start' => '12:30', 'end' => '13:30']]
                        ],
                    ]
                ]
            ],
            5 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'tuesday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'wednesday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'thursday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'friday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'saturday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                        'sunday' => ['working_hours' => ['start' => '00:00', 'end' => '23:59']],
                    ]
                ]
            ],
            6 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'tuesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'wednesday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'thursday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                        'friday' => ['working_hours' => ['start' => '08:00', 'end' => '20:00']],
                    ]
                ]
            ],
            7 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '18:00'],
                            'breaks' => [['start' => '14:00', 'end' => '15:00']]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '20:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '09:00', 'end' => '21:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ],
                    'holidays' => ['01-01', '01-02', '01-07', '03-08', '05-01', '05-09']
                ]
            ],
            8 => [
                'type' => 'static',
                'schedule' => [
                    'days' => [
                        'monday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'tuesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'wednesday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'thursday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '22:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '16:00', 'end' => '16:30']
                            ]
                        ],
                        'friday' => [
                            'working_hours' => ['start' => '08:00', 'end' => '20:00'],
                            'breaks' => [
                                ['start' => '12:15', 'end' => '13:15'],
                                ['start' => '15:00', 'end' => '15:30']
                            ]
                        ],
                        'saturday' => [
                            'working_hours' => ['start' => '10:00', 'end' => '16:00'],
                            'breaks' => [['start' => '13:15', 'end' => '14:15']]
                        ]
                    ],
                    'holidays' => ['01-01', '01-02', '01-07', '02-23', '03-08', '05-01', '05-09', '06-12', '11-04']
                ]
            ]
        ];

        return $timetables[$scenarioId] ?? $timetables[1];
    }
}
