<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

use \Exception;

class ScheduleDatabaseTest extends \PHPUnit\Framework\TestCase {
    public function testFirstSessionComposition() {
        // (1a) First and only session is AVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 7200,
                ],
            ]
        );

        // (1b) First session is AVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (1c) First session is AVAILABLE, second shift starts 30 minutes in
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '#S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (2a) First and only session is UNAVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'x', 'x' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 7200,
                ],
            ]
        );

        // (2b) First session is UNAVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'x', 'S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (2c) First shift is UNAVAILABLE, second shift starts 30 minutes in
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'x', '#S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (3a) First and only session is a SHIFT
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1', 'S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (3b) First session is a SHIFT
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1', 'S2' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 3600,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S2',
                ],
            ]
        );

        // (3c) First session is a SHIFT that starts 30 minutes in
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '#S1', 'S2' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 1800,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 1800,
                    'end'       => 3600,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S2',
                ],
            ]
        );

        // (4a) First session is a SHIFT that ends 30 minutes in, followed by AVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1#', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 1800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 1800,
                    'end'       => 7200,
                ],
            ]
        );

        // (4b) First session is a SHIFT that ends 30 minutes in, followed by UNAVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1#', 'x' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 1800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 1800,
                    'end'       => 7200,
                ],
            ]
        );

        // (4c) First session is a SHIFT that ends 30 minutes in, followed by SHIFT
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1#', 'S2' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 1800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 1800,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S2',
                ],
            ]
        );

        // (4d) First session is a SHIFT that ends 30 minutes in, followed by a SHIFT at 30 minutes
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1#', '#S2' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 1800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 1800,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 7200,
                    'shift'     => 'S2',
                ],
            ]
        );
    }

    public function testMidScheduleSessionComposition() {
        // (1a) Regular, full-hour single-hour shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 7200,
                    'end'       => 10800,
                ]
            ]
        );

        // (1b) Regular, full-hour multi-hour shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1', 'S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 10800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 10800,
                    'end'       => 14400,
                ]
            ]
        );

        // (1c) Regular, half an hour shift (1st half of the hour)
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1#', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 5400,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 5400,
                    'end'       => 10800,
                ]
            ]
        );

        // (1d) Regular, half an hour shift (2nd half of the hour)
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '#S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 7200,
                    'end'       => 10800,
                ]
            ]
        );

        // (2a) Regular, off-hour single-hour shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '#S1', 'S1#', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 9000,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 9000,
                    'end'       => 14400,
                ]
            ]
        );

        // (2b) Regular, off-hour multi-hour shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '#S1', 'S1', 'S1#', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 12600,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 12600,
                    'end'       => 18000,
                ]
            ]
        );

        // (3a) Half an hour break at the beginning of the shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1#', 'S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 5400,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 5400,
                    'end'       => 7200,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 7200,
                    'end'       => 10800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 10800,
                    'end'       => 14400,
                ]
            ]
        );

        // (3b) Half an hour break at the end of the shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1', '#S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 7200,
                    'end'       => 9000,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 9000,
                    'end'       => 10800,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 10800,
                    'end'       => 14400,
                ]
            ]
        );

        // (4) Hour break in the middle of a shift
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1', 'S1#', '#S1', 'S1', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 9000,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 9000,
                    'end'       => 12600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 12600,
                    'end'       => 18000,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 18000,
                    'end'       => 21600,
                ]
            ]
        );

        // (5) Half an hour break between two shifts
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1', '#S2', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 7200,
                    'end'       => 9000,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 9000,
                    'end'       => 10800,
                    'shift'     => 'S2',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 10800,
                    'end'       => 14400,
                ]
            ]
        );
    }

    public function testFinalSessionComposition() {
        // (1) Final session is AVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 7200,
                ],
            ]
        );

        // (2) Final session is UNAVAILABLE
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'x', 'x' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 7200,
                ],
            ]
        );

        // (3) Final session is a SHIFT in a single block
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1', 'S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );

        // (4a) Final session is a SHIFT ending 30 minutes early, with another shift ahead of it
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ 'S1', 'S1#' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 0,
                    'end'       => 5400,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 5400,
                    'end'       => 7200,
                ],
            ]
        );

        // (4b) Final session is a SHIFT ending 30 minutes early
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', 'S1#' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 3600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 5400,
                    'shift'     => 'S1',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 5400,
                    'end'       => 7200,
                ],
            ]
        );

        // (5) Final session is a SHIFT starting 30 minutes in
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '', '#S1' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 0,
                    'end'       => 5400,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 5400,
                    'end'       => 7200,
                    'shift'     => 'S1',
                ],
            ]
        );
    }

    public function testCombinationalComposition() {
        // Single test case that combines a number of the edge-cases into a single schedule, and
        // verifies that the expected schedule is parsed and composed based on the input.
        $this->assertEquals(
            ScheduleDatabase::composeSchedule(0, [ '#SB', 'E', 'E#', '#B', '', '', 'C#' ]),
            [
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 0,
                    'end'       => 1800,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 1800,
                    'end'       => 3600,
                    'shift'     => 'SB',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 3600,
                    'end'       => 9000,
                    'shift'     => 'E',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 9000,
                    'end'       => 12600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 12600,
                    'end'       => 14400,
                    'shift'     => 'B',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_AVAILABLE,
                    'start'     => 14400,
                    'end'       => 21600,
                ],
                [
                    'type'      => ScheduleDatabase::STATE_SHIFT,
                    'start'     => 21600,
                    'end'       => 23400,
                    'shift'     => 'C',
                ],
                [
                    'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                    'start'     => 23400,
                    'end'       => 25200,
                ],
            ]
        );
    }

    // Flip around the order of the |$expected| and |$actual| arguments to the |assertEqual| call.
    // While this technically doesn't matter, failure messages end up being a lot clearer.
    public static function assertEquals($expected, $actual, $message = ''): void {
        \PHPUnit\Framework\TestCase::assertEquals($actual, $expected, $message);
    }
}
