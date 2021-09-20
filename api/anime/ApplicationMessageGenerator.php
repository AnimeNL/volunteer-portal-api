<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

use Anime\Storage\Model\Registration;

// Has the ability to generate application messages that will be shared with the volunteering leads
// by e-mail. Will highlight differences, and include meta-information about the application.
class ApplicationMessageGenerator {
    public static function Generate(
            Registration $registration, string $event, string $firstName, string $lastName,
            string $dateOfBirth, string $emailAddress, string $phoneNumber,
            string $gender, string $shirtSize, string $preferences,
            bool $available, bool $hotel, bool $whatsApp) {

        $message  = self::GenerateHeader($event);
        $message .= self::GenerateTableHeader();

        $message .= self::TextualRow('First name', $firstName, $registration->getFirstName());
        $message .= self::TextualRow('Last name', $lastName, $registration->getLastName());
        $message .= self::TextualRow('Gender', ucfirst($gender), $registration->getGender());
        $message .= self::TextualRow(
                'Date of birth', $dateOfBirth, $registration->getDateOfBirth());
        $message .= self::TextualRow(
                'E-mail address', $emailAddress, $registration->getEmailAddress());
        $message .= self::TextualRow(
                'Phone number', $phoneNumber, $registration->getPhoneNumber());

        $message .= self::TextualRow('T-shirt size', $shirtSize, null);
        $message .= self::TextualRow('Preferences', $preferences, null);

        $message .= self::BooleanRow('Fully available?', $available);
        $message .= self::BooleanRow('Hotel reservation?', $hotel);
        $message .= self::BooleanRow('WhatsApp group?', $whatsApp);

        $message .= self::GenerateTableFooter();
        return $message;
    }

    private static function GenerateHeader(string $event): string {
        return '<p>A volunteering application has been received for the "' . $event . '" event, ' .
                       'originating from the IP address ' . $_SERVER['REMOTE_ADDR'] . '.</p>';
    }

    private static function GenerateTableHeader(): string {
        return '<table cellpadding=4 cellspacing=2>';
    }

    private static function TextualRow(string $label, string $left, ?string $right): string {
        $code = '<tr><td><b>' . $label . '</b></td><td>' . $left;

        if ($right && strtolower($left) !== strtolower($right))
            $code .= ' <font color="red">(was: ' . $right . ')</font>';

        return $code . '</td></tr>';
    }

    private static function BooleanRow(string $label, bool $value): string {
        return '<tr><td><b>' . $label . '</b></td><td>' . ($value ? 'Yes' : 'No') . '</td></tr>';
    }

    private static function GenerateTableFooter(): string {
        return '</table>';
    }

}
