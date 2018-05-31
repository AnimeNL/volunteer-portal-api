// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Target offset, based on UTC, based on which dates and times should be presented.
const TARGET_TIMEZONE_OFFSET = +2;

// Array with three-character representations of the days of the week.
const SHORT_DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Array with full textual representations of the days of the week.
const LONG_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// In order to faciliate testing on the portal, the date and time globally can be faked by using
// the DateUtils.setMockTime() method and passing in the intended timestamp.
let mockedPageLoadTime = null;
let actualPageLoadTime = Date.now();

// A collection of utilities related to times and dates. The server communicates UNIX timestamps to
// the client based on the UTC timezone, whereas they have to be presented in CEST (UTC+2). Beyond
// that, this class also offers the ability to set a mocked time, enabling the system to be tested.
class DateUtils {
    // Returns the current UNIX timestamp based on the UTC timezone. If a mocked time has been set,
    // it will be used instead of the actual time on the local user's device.
    static getTime() {
        const currentTime = Date.now();

        if (!mockedPageLoadTime)
            return currentTime;

        const difference = currentTime - actualPageLoadTime;
        return mockedPageLoadTime + difference;
    }

    // Updates the current time to |time|. When |time| is set to NULL the actual time will be used,
    // otherwise the value of |time| will be offset against the current time to determine the faked
    // time, which is an important tool in testing the portal.
    static setMockTime(time) {
        mockedPageLoadTime = time;
        actualPageLoadTime = Date.now();
    }

    // Converts |time|, which must be a unix timestamp in millisecond granularity in UTC, to the
    // target timezone that is to be used by this application. Returns a Date instance.
    static toTargetTimezone(time) {
        return new Date(time + TARGET_TIMEZONE_OFFSET * 60 * 60 * 1000);
    }

    // Formats the date in YYYY-MM-DD format. Assumes |date| to be a Date instance containing the
    // correct time in UTC time.
    static formatDate(date) {
        let formattedDate = '';

        formattedDate += date.getUTCFullYear();
        formattedDate += '-' + ('0' + (date.getUTCMonth() + 1)).substr(-2);
        formattedDate += '-' + ('0' + date.getUTCDate()).substr(-2);

        return formattedDate;
    }

    // Formats the time in HH:MM(:SS) format. Assumes |date| to be a Date instance containing the
    // correct time in UTC time. Including the seconds can be controlled using |includeSeconds|.
    static formatTime(date, includeSeconds = false) {
        let formattedTime = '';

        formattedTime += ('0' + date.getUTCHours()).substr(-2);
        formattedTime += ':' + ('0' + date.getUTCMinutes()).substr(-2);

        if (includeSeconds)
            formattedTime += ':' + ('0' + date.getUTCSeconds()).substr(-2);

        return formattedTime;
    }

    // Formats the timezone offset for |offsetHours| into an ISO 8601-compatible representation.
    static formatTimezoneOffset(offsetHours) {
        const offset = offsetHours * 3600;
        const prefix = offset >= 0 ? '+' : '-';

        let formattedOffset = prefix;
        formattedOffset += ('0' + Math.floor(offset / 3600)).substr(-2);
        formattedOffset += ':' + ('0' + Math.floor((offset % 3600) / 60)).substr(-2);

        return formattedOffset;
    }

    // Formats the current day of the week for |date|. Assumes |date| to be a Date instance
    // containing the current time in UTC time. Returns long or short days dependong in |shortDays|.
    static formatDay(date, shortDays = false) {
        const currentDay = date.getUTCDay();

        if (shortDays)
            return SHORT_DAYS[currentDay];
        else
            return LONG_DAYS[currentDay];
    }

    // Formats |time| to be displayed according to the |format|. The |time| is expected to be a
    // UNIX timestamp in millisecond granularity in the UTC timezone.
    static format(time, format) {
        const localDate = DateUtils.toTargetTimezone(time);

        switch (format) {
            case DateUtils.FORMAT_ISO_8601:
                return DateUtils.formatDate(localDate) + 'T' +
                       DateUtils.formatTime(localDate, true /* includeSeconds */) +
                       DateUtils.formatTimezoneOffset(TARGET_TIMEZONE_OFFSET);

            case DateUtils.FORMAT_DAY_SHORT_TIME:
                return DateUtils.formatDay(localDate, true /* shortDays */) + ' ' +
                       DateUtils.formatTime(localDate, false /* includeSeconds */);

            case DateUtils.FORMAT_SHORT_TIME:
                return DateUtils.formatTime(localDate, false /* includeSeconds */);

            case DateUtils.FORMAT_SHORT_DAY:
                return DateUtils.formatDay(localDate, false /* shortDays */);

            default:
                throw new Error('Unexpected format type: ' + format);
        }
    }

    // Returns whether the given |time| takes place during the time.
    static isNight(time) {
        const localDate = DateUtils.toTargetTimezone(time);
        return localDate.getUTCHours() < 8;
    }
}

// Formats the time as "YYYY-MM-DDTHH:II:SS+[OFFSET]" (ISO 8601).
DateUtils.FORMAT_ISO_8601 = 0;

// Formats the time as "DAY HH:II".
DateUtils.FORMAT_DAY_SHORT_TIME = 1;

// Formats the time as "HH:II".
DateUtils.FORMAT_SHORT_TIME = 2;

// Formats the time as just the name of the day.
DateUtils.FORMAT_SHORT_DAY = 3;

module.exports = DateUtils;

////////////////////////////////////////////////////////////////////////////////////////////////////
// TODO: This exposure exist whilst I transition the existing schedule implementation.

global.DateUtils = DateUtils;
