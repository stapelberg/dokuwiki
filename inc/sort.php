<?php
/**
 * DokuWiki sort functions
 *
 * When "intl" extension is available, all sorts are done using a collator.
 * Otherwise, primitive PHP functions are called.
 *
 * The collator is created using the locale given in $conf['lang'].
 * It always uses case insensitive "natural" ordering in its collation.
 * The fallback solution uses the primitive PHP functions that return almost the same results.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Moisés Braga Ribeiro <moisesbr@gmail.com>
 */

/* @var bool $intl_extension_available */
$intl_extension_available = class_exists('Collator');

/**
 * Initialize a collator using $conf['lang'] as the locale.
 * The initialization is done only once.
 * The collation takes "natural ordering" into account, that is, "page 2" is before "page 10".
 *
 * @return Collator Returns a configured collator or NULL if the collator cannot be created.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function _get_collator() {
    global $conf, $intl_extension_available;
    static $collator = NULL;

    if ($intl_extension_available && !isset($collator)) {
        $collator = Collator::create($conf['lang']);
        if (isset($collator)) {
            $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
            dbglog('Collator created with locale "' . $conf['lang'] . '": numeric collation on, ' .
                   'valid locale "' . $collator->getLocale(Locale::VALID_LOCALE) . '", ' .
                   'actual locale "' . $collator->getLocale(Locale::ACTUAL_LOCALE) . '"');
        }
    }
    return $collator;
}

// strcmp()
// inc/fulltext.php:373
// inc/search.php:371
// lib/plugins/acl/admin.php:307
// strcasecmp()
// inc/Ui/Admin.php:162
// strnatcmp()
// inc/media.php:1986 --> 1984
/**
 * Drop-in replacement for strcmp(), strcasecmp(), strnatcmp() and strnatcasecmp().
 * It uses a collator-based comparison, or strnatcasecmp() as a fallback.
 *
 * @param string $str1 The first string.
 * @param string $str2 The second string.
 * @return int Returns < 0 if $str1 is less than $str2; > 0 if $str1 is greater than $str2, and 0 if they are equal.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function intl_strcmp($str1, $str2) {
    $collator = _get_collator();
    if (isset($collator))
        return $collator->compare($str1, $str2);
    else
        return strnatcasecmp($str1, $str2);
}

// sort()
// bin/wantedpages.php:86
// inc/Ajax.php:101
// inc/fulltext.php:183
// inc/fulltext.php:214
// lib/plugins/acl/admin.php:592
// lib/plugins/acl/admin.php:611
// lib/plugins/authpdo/auth.php:602
/**
 * Drop-in replacement for sort().
 * It uses a collator-based sort, or sort() with flags SORT_NATURAL and SORT_FLAG_CASE as a fallback.
 *
 * @param array $array The input array.
 * @return bool Returns TRUE on success or FALSE on failure.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function intl_sort(&$array) {
    $collator = _get_collator();
    if (isset($collator))
        return $collator->sort($array);
    else
        return sort($array, SORT_NATURAL | SORT_FLAG_CASE);
}

// ksort()
// bin/wantedpages.php:80
// inc/Ui/Search.php:387
// lib/plugins/acl/admin.php:612 + 613 (added)
// lib/plugins/authad/auth.php:735
// lib/plugins/authpdo/auth.php:635
// lib/plugins/authplain/auth.php:301 + 338 (added)
/**
 * Drop-in replacement for ksort().
 * It uses a collator-based sort, or ksort() with flags SORT_NATURAL and SORT_FLAG_CASE as a fallback.
 *
 * @param array $array The input array.
 * @return bool Returns TRUE on success or FALSE on failure.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function intl_ksort(&$array) {
    $collator = _get_collator();
    if (isset($collator))
        return uksort($array, array($collator, 'compare'));
    else
        return ksort($array, SORT_NATURAL | SORT_FLAG_CASE);
}

// lib/plugins/authad/adLDAP/classes/adLDAPContacts.php:274
// lib/plugins/authad/adLDAP/classes/adLDAPGroups.php:527
// lib/plugins/authad/adLDAP/classes/adLDAPUsers.php:571
// lib/plugins/authad/adLDAP/classes/adLDAPUsers.php:634
// lib/plugins/authldap/auth.php:416
/**
 * Drop-in replacement for asort(), natsort() and natcasesort().
 * It uses a collator-based sort, or asort() with flags SORT_NATURAL and SORT_FLAG_CASE as a fallback.
 *
 * @param array $array The input array.
 * @return bool Returns TRUE on success or FALSE on failure.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function intl_asort(&$array) {
    $collator = _get_collator();
    if (isset($collator))
        return $collator->asort($array);
    else
        return asort($array, SORT_NATURAL | SORT_FLAG_CASE);
}

// natsort()
// inc/search.php:52
// inc/search.php:54
/**
 * Drop-in replacement for asort(), natsort() and natcasesort() when the parameter is an array of filenames.
 * Filenames may not be equal to page names, depending on the setting in $conf['fnencode'],
 * so the correct behavior is to sort page names and reflect this sorting in the filename array.
 *
 * @param array $array The input array.
 * @return bool Returns TRUE on success or FALSE on failure.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function intl_asortFN(&$array) {
    $collator = _get_collator();
    if (isset($collator))
        return uasort($array, '_sort_filenames_with_collator');
    else
        return uasort($array, '_sort_filenames_without_collator');
}

/**
 * Collator-based string comparison for filenames.
 * The filenames are converted to page names with utf8_decodeFN() before the comparison.
 * 
 * @param string $fn1 The first filename.
 * @param string $fn2 The second filename.
 * @return int Returns < 0 if $fn1 is less than $fn2; > 0 if $fn1 is greater than $fn2, and 0 if they are equal.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function _sort_filenames_with_collator($fn1, $fn2) {
    $collator = _get_collator();
    return $collator->compare(utf8_decodeFN($fn1), utf8_decodeFN($fn2));
}

/**
 * Fallback string comparison for filenames, using strnatcasecmp().
 * The filenames are converted to page names with utf8_decodeFN() before the comparison.
 * 
 * @param string $fn1 The first filename.
 * @param string $fn2 The second filename.
 * @return int Returns < 0 if $fn1 is less than $fn2; > 0 if $fn1 is greater than $fn2, and 0 if they are equal.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 */
function _sort_filenames_without_collator($fn1, $fn2) {
    return strnatcasecmp(utf8_decodeFN($fn1), utf8_decodeFN($fn2));
}