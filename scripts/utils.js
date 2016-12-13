// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// A collection of shared utility functions between different files.
class Utils {
    // Creates a slug out of |text|. Correctly handles a series of accents that were silently
    // dropped in the previous version of the volunteer portal.
    static createSlug(text) {
        const replacements = { 'à': 'a', 'á': 'a', 'â': 'a', 'ã': 'a', 'ä': 'a', 'å': 'a', 'ò': 'o',
                               'ó': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o', 'ø': 'o', 'è': 'e', 'é': 'e',
                               'ê': 'e', 'ë': 'e', 'ð': 'o', 'ç': 'c', 'ì': 'i', 'í': 'i', 'î': 'i',
                               'ï': 'i', 'ù': 'u', 'ú': 'u', 'û': 'u', 'ü': 'u', 'ñ': 'u', 'š': 's',
                               'ÿ': 'y', 'ý': 'y' };

        let slug = '';
        for (let i = 0; i < text.length; ++i) {
            const character = text[i].toLowerCase();

            if (replacements.hasOwnProperty(character))
                slug += replacements[character];
            else
                slug += character;
        }

        return slug.replace(/[^\w ]+/g, '')
                   .replace(/\s+/g, '-');
    }
};

module.exports = Utils;
